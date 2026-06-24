<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mrsuner\Coupon\Events\CouponRedeemed;
use Mrsuner\Coupon\Exceptions\CouponNotRedeemableException;
use Mrsuner\Coupon\Exceptions\DuplicateCouponCodeException;
use Mrsuner\Coupon\Models\CouponCode;
use Mrsuner\Coupon\Models\CouponRedemption;
use Mrsuner\Coupon\Services\CouponService;
use Mrsuner\Coupon\Tests\Fixtures\User;
use Mrsuner\Coupon\Tests\TestCase;
use Mrsuner\Coupon\ValueObjects\ValidationResult;
use RuntimeException;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CouponService
    {
        return $this->app->make(CouponService::class);
    }

    public function test_generates_code_with_correct_format(): void
    {
        $coupon = $this->service()->generate([
            'type'  => 'percent_off',
            'value' => ['percent' => 20],
        ]);

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/', $coupon->code);
    }

    public function test_generates_unique_code_on_collision(): void
    {
        // Shrink the keyspace to 2 possible codes so the retry loop is exercised.
        config()->set('coupon.generation.length', 1);
        config()->set('coupon.generation.charset', 'AB');

        $first  = $this->service()->generate(['type' => 'custom', 'value' => []]);
        $second = $this->service()->generate(['type' => 'custom', 'value' => []]);

        $this->assertNotSame($first->code, $second->code);
    }

    public function test_generate_throws_on_duplicate_custom_code(): void
    {
        $this->service()->generate(['code' => 'DUPE', 'type' => 'custom', 'value' => []]);

        $this->expectException(DuplicateCouponCodeException::class);

        $this->service()->generate(['code' => 'DUPE', 'type' => 'custom', 'value' => []]);
    }

    public function test_generates_bulk_codes_with_prefix(): void
    {
        $codes = $this->service()->generateBulk(5, [
            'code'  => 'LAUNCH',
            'type'  => 'free_months',
            'value' => ['months' => 1],
        ]);

        $this->assertCount(5, $codes);
        $this->assertSame(5, $codes->pluck('code')->unique()->count());

        foreach ($codes as $coupon) {
            $this->assertStringStartsWith('LAUNCH-', $coupon->code);
        }
    }

    public function test_validate_returns_valid_for_active_code(): void
    {
        $this->service()->generate(['code' => 'OK', 'type' => 'custom', 'value' => []]);

        $result = $this->service()->validate('OK');

        $this->assertTrue($result->valid);
        $this->assertNull($result->error);
        $this->assertInstanceOf(CouponCode::class, $result->coupon);
    }

    public function test_validate_returns_not_found_for_unknown_code(): void
    {
        $result = $this->service()->validate('NOPE');

        $this->assertFalse($result->valid);
        $this->assertSame(ValidationResult::NOT_FOUND, $result->error);
    }

    public function test_validate_returns_inactive_for_deactivated_code(): void
    {
        $this->service()->generate(['code' => 'OFF', 'type' => 'custom', 'value' => [], 'is_active' => false]);

        $result = $this->service()->validate('OFF');

        $this->assertSame(ValidationResult::INACTIVE, $result->error);
    }

    public function test_validate_returns_expired_when_past_expiry(): void
    {
        $this->service()->generate([
            'code'         => 'OLD',
            'type'         => 'custom',
            'value'        => [],
            'restrictions' => ['expires_at' => now()->subDay()->toIso8601String()],
        ]);

        $result = $this->service()->validate('OLD');

        $this->assertSame(ValidationResult::EXPIRED, $result->error);
    }

    public function test_validate_returns_exhausted_when_max_uses_reached(): void
    {
        $coupon = $this->service()->generate([
            'code'         => 'MAXED',
            'type'         => 'custom',
            'value'        => [],
            'restrictions' => ['max_uses' => 1],
        ]);
        $coupon->update(['times_redeemed' => 1]);

        $result = $this->service()->validate('MAXED');

        $this->assertSame(ValidationResult::EXHAUSTED, $result->error);
    }

    public function test_validate_returns_user_limit_when_per_user_exceeded(): void
    {
        $user = User::factory()->create();

        $this->service()->generate([
            'code'         => 'ONCE',
            'type'         => 'custom',
            'value'        => [],
            'restrictions' => ['per_user' => 1],
        ]);

        $this->service()->redeem('ONCE', $user);

        $result = $this->service()->validate('ONCE', $user);

        $this->assertSame(ValidationResult::USER_LIMIT, $result->error);
    }

    public function test_validate_returns_min_amount_when_amount_is_missing_or_too_low(): void
    {
        $this->service()->generate([
            'code'         => 'MIN',
            'type'         => 'amount_off',
            'value'        => ['amount' => 500],
            'restrictions' => ['min_amount' => 10_000],
        ]);

        $missing = $this->service()->validate('MIN');
        $tooLow = $this->service()->validate('MIN', context: ['amount_cents' => 9_999]);

        $this->assertSame(ValidationResult::MIN_AMOUNT, $missing->error);
        $this->assertSame(ValidationResult::MIN_AMOUNT, $tooLow->error);
    }

    public function test_validate_accepts_coupon_when_min_amount_is_met(): void
    {
        $this->service()->generate([
            'code'         => 'ENOUGH',
            'type'         => 'amount_off',
            'value'        => ['amount' => 500],
            'restrictions' => ['min_amount' => 10_000],
        ]);

        $result = $this->service()->validate('ENOUGH', context: ['amount_cents' => 10_000]);

        $this->assertTrue($result->valid);
    }

    public function test_redeem_creates_redemption_record(): void
    {
        $user = User::factory()->create();
        $this->service()->generate(['code' => 'GO', 'type' => 'custom', 'value' => []]);

        $redemption = $this->service()->redeem('GO', $user, ['order_id' => 42]);

        $this->assertInstanceOf(CouponRedemption::class, $redemption);
        $this->assertDatabaseHas('coupon_redemptions', [
            'id'              => $redemption->id,
            'redeemable_type' => $user->getMorphClass(),
            'redeemable_id'   => $user->getKey(),
        ]);
        $this->assertSame(['order_id' => 42], $redemption->context);
    }

    public function test_redeem_increments_times_redeemed(): void
    {
        $user   = User::factory()->create();
        $coupon = $this->service()->generate(['code' => 'INC', 'type' => 'custom', 'value' => []]);

        $this->service()->redeem('INC', $user);

        $this->assertSame(1, (int) $coupon->fresh()->times_redeemed);
    }

    public function test_redeem_rejects_exhausted_coupon_without_writing(): void
    {
        $user   = User::factory()->create();
        $coupon = $this->service()->generate([
            'code'         => 'LIMIT',
            'type'         => 'custom',
            'value'        => [],
            'restrictions' => ['max_uses' => 1],
        ]);

        $coupon->update(['times_redeemed' => 1]);

        try {
            $this->service()->redeem('LIMIT', $user);
            $this->fail('Expected CouponNotRedeemableException.');
        } catch (CouponNotRedeemableException $e) {
            $this->assertSame(ValidationResult::EXHAUSTED, $e->getValidationResult()->error);
        }

        $this->assertSame(0, CouponRedemption::query()->count());
        $this->assertSame(1, (int) $coupon->fresh()->times_redeemed);
    }

    public function test_redeem_rejects_per_user_limit_without_writing(): void
    {
        $user   = User::factory()->create();
        $coupon = $this->service()->generate([
            'code'         => 'USER-LIMIT',
            'type'         => 'custom',
            'value'        => [],
            'restrictions' => ['per_user' => 1],
        ]);

        $this->service()->redeem('USER-LIMIT', $user);

        try {
            $this->service()->redeem('USER-LIMIT', $user);
            $this->fail('Expected CouponNotRedeemableException.');
        } catch (CouponNotRedeemableException $e) {
            $this->assertSame(ValidationResult::USER_LIMIT, $e->getValidationResult()->error);
        }

        $this->assertSame(1, CouponRedemption::query()->count());
        $this->assertSame(1, (int) $coupon->fresh()->times_redeemed);
    }

    public function test_redeem_rejects_min_amount_without_writing(): void
    {
        $user   = User::factory()->create();
        $coupon = $this->service()->generate([
            'code'         => 'ORDER-MIN',
            'type'         => 'amount_off',
            'value'        => ['amount' => 500],
            'restrictions' => ['min_amount' => 10_000],
        ]);

        try {
            $this->service()->redeem('ORDER-MIN', $user, ['amount_cents' => 9_999]);
            $this->fail('Expected CouponNotRedeemableException.');
        } catch (CouponNotRedeemableException $e) {
            $this->assertSame(ValidationResult::MIN_AMOUNT, $e->getValidationResult()->error);
        }

        $this->assertSame(0, CouponRedemption::query()->count());
        $this->assertSame(0, (int) $coupon->fresh()->times_redeemed);
    }

    public function test_redeem_allows_min_amount_when_amount_is_met(): void
    {
        $user = User::factory()->create();
        $this->service()->generate([
            'code'         => 'ORDER-OK',
            'type'         => 'amount_off',
            'value'        => ['amount' => 500],
            'restrictions' => ['min_amount' => 10_000],
        ]);

        $redemption = $this->service()->redeem('ORDER-OK', $user, ['amount_cents' => 10_000]);

        $this->assertInstanceOf(CouponRedemption::class, $redemption);
        $this->assertSame(['amount_cents' => 10_000], $redemption->context);
    }

    public function test_redeem_fires_coupon_redeemed_event(): void
    {
        Event::fake([CouponRedeemed::class]);

        $user = User::factory()->create();
        $this->service()->generate(['code' => 'EVT', 'type' => 'custom', 'value' => []]);

        $this->service()->redeem('EVT', $user);

        Event::assertDispatched(CouponRedeemed::class, function (CouponRedeemed $event) use ($user): bool {
            return $event->coupon->code === 'EVT'
                && $event->redeemable->is($user)
                && $event->redemption->exists;
        });
    }

    public function test_redeem_stores_snapshot_at_redemption_time(): void
    {
        $user   = User::factory()->create();
        $coupon = $this->service()->generate([
            'code'  => 'SNAP',
            'type'  => 'percent_off',
            'value' => ['percent' => 20],
        ]);

        $redemption = $this->service()->redeem('SNAP', $user);

        // Mutate the coupon after redemption — snapshot must remain unchanged.
        $coupon->update(['name' => 'edited later']);

        $this->assertSame(
            ['type' => 'percent_off', 'value' => ['percent' => 20]],
            $redemption->fresh()->snapshot,
        );
    }

    public function test_redeem_throws_when_validation_fails(): void
    {
        $user = User::factory()->create();

        try {
            $this->service()->redeem('MISSING', $user);
            $this->fail('Expected CouponNotRedeemableException.');
        } catch (CouponNotRedeemableException $e) {
            $this->assertSame(ValidationResult::NOT_FOUND, $e->getMessage());
            $this->assertSame(ValidationResult::NOT_FOUND, $e->getValidationResult()->error);
        }
    }

    public function test_redeem_is_atomic_on_database_failure(): void
    {
        Event::fake([CouponRedeemed::class]);

        $user   = User::factory()->create();
        $coupon = $this->service()->generate(['code' => 'ATOM', 'type' => 'custom', 'value' => []]);

        // Force the counter update inside the transaction to blow up.
        CouponCode::updating(function (): void {
            throw new RuntimeException('simulated db failure');
        });

        try {
            $this->service()->redeem('ATOM', $user);
            $this->fail('Expected the redemption to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated db failure', $e->getMessage());
        }

        // Both writes rolled back, and the event never fired.
        $this->assertSame(0, CouponRedemption::query()->count());
        $this->assertSame(0, (int) $coupon->fresh()->times_redeemed);
        Event::assertNotDispatched(CouponRedeemed::class);
    }
}
