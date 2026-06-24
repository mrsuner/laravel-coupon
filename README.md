# laravel-coupon

Coupon lifecycle management for Laravel — generation, validation, and redemption
recording. An official companion package for
[`mrsuner/laravel-api-boilerplate`](https://github.com/mrsuner/laravel-api-boilerplate).

## Design philosophy

This package owns the **coupon lifecycle only**. It has zero knowledge of billing
systems, subscription engines, or application-specific business logic. The boundary
is enforced by a single rule:

> **The package fires `CouponRedeemed` and stops. The host application listens and acts.**

This means:

- No dependency on `laravel/cashier`, `laravel/cashier-paddle`, or any billing package.
- No dependency on your `Order`, `Subscription`, or `Credit` models.
- Any SaaS built on the boilerplate can install it regardless of its billing stack.

What connects the package to your business logic is **one event class**
(`CouponRedeemed`) and **one public service** (`CouponService`). Everything else is
internal.

Works in any Laravel application. When installed on top of
[`mrsuner/laravel-api-boilerplate`](https://github.com/mrsuner/laravel-api-boilerplate)
it auto-wires into the boilerplate's admin stack; on a plain Laravel app it
falls back to a Sanctum admin-ability API (see Configuration).

## Requirements

- PHP `^8.2`
- Laravel `^11.0 | ^12.0`
- `laravel/sanctum` for the default admin-API authentication.

## Installation

```bash
composer require mrsuner/laravel-coupon
php artisan vendor:publish --tag=coupon-config
php artisan migrate
```

The service provider is auto-discovered. It registers the migrations, the
`CouponService` singleton, and the admin API routes.

## Configuration

`config/coupon.php`:

| Key | Description |
| --- | --- |
| `generation.length / prefix / suffix / charset` | Auto-generated code format. Default: 8 unambiguous uppercase chars. |
| `table_names.*` | Override table names if they collide with your schema. |
| `redeemable_morph_map` | The morph alias for your redeemable model, if you use morph maps. |
| `route.enabled` | Master switch for the admin API (default `true`). |
| `route.prefix / name` | Where the admin API mounts and its route-name prefix. |
| `route.middleware` | `null` = auto-detect (see below); or an explicit array. |

**Admin middleware auto-detection.** When `route.middleware` is `null` (the
default), the package picks a stack at boot:

- If the boilerplate's `App\Http\Middleware\InternalIpWhitelist` class exists, it
  uses the full boilerplate admin stack
  (`throttle:60,1` + `InternalIpWhitelist` + `auth:sanctum` + `ability:admin`,
  plus `EnsureAdminAccess` when available).
- Otherwise (a plain Laravel app) it falls back to
  `['auth:sanctum', 'ability:admin']`.

Set an explicit array to take full control. The package always appends the
framework's route-model-binding middleware automatically.

**Route gate.** Admin routes are skipped (every endpoint `404`s) when
`config('coupon.route.enabled')` is false, or when the host defines
`config('boilerplate.admin.enabled')` and sets it to false.

## Public API — `CouponService`

Resolve it from the container: `app(\Mrsuner\Coupon\Services\CouponService::class)`.

```php
use Mrsuner\Coupon\Services\CouponService;

$coupons = app(CouponService::class);

// Create a single code (auto-generated if "code" is omitted).
$coupon = $coupons->generate([
    'type'  => 'percent_off',
    'value' => ['percent' => 20],
    'restrictions' => ['max_uses' => 500, 'per_user' => 1, 'expires_at' => '2026-12-31T23:59:59Z'],
]);

// Bulk-generate; "code" becomes a prefix (LAUNCH-A3F9K2).
$codes = $coupons->generateBulk(50, ['code' => 'LAUNCH', 'type' => 'free_months', 'value' => ['months' => 1]]);

// Validate without recording (returns a ValidationResult value object).
$result = $coupons->validate('LAUNCH20', $user);
if (! $result->valid) {
    // $result->error is one of ValidationResult::NOT_FOUND|INACTIVE|EXPIRED|EXHAUSTED|USER_LIMIT
}

// Validate, record, and fire CouponRedeemed (atomic).
$redemption = $coupons->redeem('LAUNCH20', $user, ['ip' => request()->ip()]);
```

`redeem()` throws `CouponNotRedeemableException` when validation fails; the
`ValidationResult` is available via `$e->getValidationResult()`.

## Providing a redeem endpoint

The package does **not** ship a user-facing redeem endpoint. Wire your own:

```php
// routes/api.php
Route::post('/redeem-coupon', RedeemCouponController::class)->middleware('auth:sanctum');

// app/Http/Controllers/RedeemCouponController.php
public function __invoke(Request $request, CouponService $coupons): JsonResponse
{
    $request->validate(['code' => ['required', 'string']]);

    $result = $coupons->validate($request->code, $request->user());
    if (! $result->valid) {
        return response()->json(['message' => __('coupon.'.$result->error)], 422);
    }

    $redemption = $coupons->redeem($request->code, $request->user(), ['ip' => $request->ip()]);

    return response()->json(['message' => 'Coupon applied.', 'redemption' => $redemption->id]);
}
```

## Reacting to redemptions

```php
// app/Providers/AppServiceProvider.php
use Mrsuner\Coupon\Events\CouponRedeemed;
use App\Listeners\ApplyCouponEffect;

public function boot(): void
{
    Event::listen(CouponRedeemed::class, ApplyCouponEffect::class);
}
```

```php
// app/Listeners/ApplyCouponEffect.php
public function handle(CouponRedeemed $event): void
{
    match ($event->coupon->type) {
        'percent_off' => $this->applyStripePromo($event->redeemable, $event->coupon->value),
        'free_months' => $this->extendTrial($event->redeemable, $event->coupon->value['months']),
        'amount_off'  => $this->applyCredit($event->redeemable, $event->coupon->value['amount']),
        default       => Log::info('Unhandled coupon type', ['type' => $event->coupon->type]),
    };

    audit_log('coupon.redeemed', $event->coupon, [
        'user'     => $event->redeemable,
        'metadata' => ['redemption_id' => $event->redemption->id],
    ]);
}
```

The package deliberately leaves Stripe/Paddle integration to your listener so the
audit trail can carry billing context (subscription id, order id, etc.).

## Admin API

All endpoints mount under `internal/admin/v1` behind the boilerplate admin stack.

| Method | URI | Name |
| --- | --- | --- |
| GET | `/coupons` | `admin.coupons.index` |
| POST | `/coupons` | `admin.coupons.store` |
| POST | `/coupons/bulk` | `admin.coupons.bulk` |
| GET | `/coupons/{coupon}` | `admin.coupons.show` |
| PATCH | `/coupons/{coupon}` | `admin.coupons.update` |
| DELETE | `/coupons/{coupon}` | `admin.coupons.destroy` |
| PATCH | `/coupons/{coupon}/activate` | `admin.coupons.activate` |
| PATCH | `/coupons/{coupon}/deactivate` | `admin.coupons.deactivate` |
| GET | `/coupons/{coupon}/redemptions` | `admin.coupons.redemptions` |
| GET | `/coupon-redemptions` | `admin.coupon-redemptions.index` |

Notes:

- `type` and `value` are **immutable** after creation (to preserve redemption
  snapshot integrity). Only `name`, `is_active`, and `restrictions` are updatable.
- Deleting a coupon with redemptions requires `?force=true`; otherwise it returns
  `422`. Deletes are soft — redemption history is always preserved.

## Exceptions

Map them in `bootstrap/app.php` if you want custom HTTP responses:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (CouponNotRedeemableException $e) {
        return response()->json(['message' => __('coupon.'.$e->getMessage())], 422);
    });
})
```

## Testing

```bash
composer install
composer test
```

The suite runs against `orchestra/testbench` with an in-memory SQLite database.

## License

MIT.
