<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Mrsuner\Coupon\Events\CouponRedeemed;
use Mrsuner\Coupon\Exceptions\CouponNotRedeemableException;
use Mrsuner\Coupon\Exceptions\DuplicateCouponCodeException;
use Mrsuner\Coupon\Models\CouponCode;
use Mrsuner\Coupon\Models\CouponRedemption;
use Mrsuner\Coupon\ValueObjects\ValidationResult;

/**
 * The single public entry point for coupon lifecycle operations.
 *
 * Bound as a singleton by CouponServiceProvider — resolve via
 * app(CouponService::class) from the host application.
 */
class CouponService
{
    /**
     * Create and persist a single coupon code.
     *
     * @param  array<string, mixed>  $attributes  code, name, type, value, restrictions
     *
     * @throws DuplicateCouponCodeException When a supplied custom code already exists.
     */
    public function generate(array $attributes): CouponCode
    {
        $code = $attributes['code'] ?? null;

        if ($code !== null) {
            if ($this->codeExists($code)) {
                throw DuplicateCouponCodeException::forCode($code);
            }
        } else {
            $code = $this->generateUniqueCode();
        }

        return CouponCode::query()->create([
            'code'         => $code,
            'name'         => $attributes['name'] ?? null,
            'type'         => $attributes['type'],
            'value'        => $attributes['value'],
            'restrictions' => $attributes['restrictions'] ?? null,
            'is_active'    => $attributes['is_active'] ?? true,
        ]);
    }

    /**
     * Generate $quantity unique codes sharing type, value, and restrictions.
     *
     * When provided, $attributes['code'] is treated as a prefix
     * (e.g. "LAUNCH" => "LAUNCH-A3F9K2").
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, CouponCode>
     */
    public function generateBulk(int $quantity, array $attributes): Collection
    {
        $prefix = $attributes['code'] ?? null;

        /** @var Collection<int, CouponCode> $created */
        $created = new Collection;

        for ($i = 0; $i < $quantity; $i++) {
            $created->push($this->generate([
                'code'         => $this->generateUniqueCode($prefix),
                'name'         => $attributes['name'] ?? null,
                'type'         => $attributes['type'],
                'value'        => $attributes['value'],
                'restrictions' => $attributes['restrictions'] ?? null,
                'is_active'    => $attributes['is_active'] ?? true,
            ]));
        }

        return $created;
    }

    /**
     * Check whether a code can be redeemed, without recording a redemption.
     */
    public function validate(string $code, ?Model $redeemable = null): ValidationResult
    {
        $coupon = CouponCode::query()->where('code', $code)->first();

        if ($coupon === null) {
            return ValidationResult::invalid(ValidationResult::NOT_FOUND);
        }

        if (! $coupon->is_active) {
            return ValidationResult::invalid(ValidationResult::INACTIVE, $coupon);
        }

        if ($coupon->isExpired()) {
            return ValidationResult::invalid(ValidationResult::EXPIRED, $coupon);
        }

        if ($coupon->isExhausted()) {
            return ValidationResult::invalid(ValidationResult::EXHAUSTED, $coupon);
        }

        if ($redeemable !== null && ! $coupon->isUsableBy($redeemable)) {
            return ValidationResult::invalid(ValidationResult::USER_LIMIT, $coupon);
        }

        return ValidationResult::valid($coupon);
    }

    /**
     * Validate, record the redemption, and fire CouponRedeemed.
     *
     * @param  array<string, mixed>  $context  Optional metadata from the host app.
     *
     * @throws CouponNotRedeemableException When validation fails.
     */
    public function redeem(string $code, Model $redeemable, array $context = []): CouponRedemption
    {
        $result = $this->validate($code, $redeemable);

        if (! $result->valid) {
            throw new CouponNotRedeemableException($result);
        }

        /** @var CouponCode $coupon */
        $coupon = $result->coupon;

        $redemption = DB::transaction(function () use ($coupon, $redeemable, $context): CouponRedemption {
            // Lock the row so concurrent redemptions can't overshoot max_uses.
            /** @var CouponCode $locked */
            $locked = CouponCode::query()->whereKey($coupon->getKey())->lockForUpdate()->first();

            $redemption = new CouponRedemption([
                'snapshot' => [
                    'type'  => $locked->type,
                    'value' => $locked->value,
                ],
                'context'     => $context !== [] ? $context : null,
                'redeemed_at' => now(),
            ]);

            $redemption->coupon()->associate($locked);
            $redemption->redeemable()->associate($redeemable);
            $redemption->save();

            $locked->increment('times_redeemed');

            return $redemption;
        });

        // Refresh so the returned coupon/redemption reflect committed state.
        $coupon->refresh();

        CouponRedeemed::dispatch($coupon, $redeemable, $redemption);

        return $redemption;
    }

    private function codeExists(string $code): bool
    {
        return CouponCode::query()->withTrashed()->where('code', $code)->exists();
    }

    /**
     * Build a unique random code, honouring the configured prefix/suffix/length.
     *
     * @param  string|null  $prefix  Overrides the configured prefix when supplied
     *                               (used by bulk generation).
     */
    private function generateUniqueCode(?string $prefix = null): string
    {
        do {
            $code = $this->buildCode($prefix);
        } while ($this->codeExists($code));

        return $code;
    }

    private function buildCode(?string $prefix = null): string
    {
        $config  = config('coupon.generation');
        $length  = max(1, (int) ($config['length'] ?? 8));
        $charset = (string) ($config['charset'] ?? 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $suffix  = (string) ($config['suffix'] ?? '');

        $prefix ??= (string) ($config['prefix'] ?? '');

        $random = $this->randomString($charset, $length);

        // When a prefix is present, join with a dash for readability.
        $head = $prefix !== '' ? $prefix.'-' : '';

        return $head.$random.$suffix;
    }

    private function randomString(string $charset, int $length): string
    {
        $max = strlen($charset) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $charset[random_int(0, $max)];
        }

        return $out;
    }
}
