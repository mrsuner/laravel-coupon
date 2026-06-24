<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mrsuner\Coupon\Services\CouponService;
use Mrsuner\Coupon\Tests\Fixtures\User;
use Mrsuner\Coupon\Tests\TestCase;

class AdminCouponRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/internal/admin/v1';

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['admin']);
    }

    public function test_admin_can_list_global_redemptions(): void
    {
        $this->actingAsAdmin();

        $service = $this->app->make(CouponService::class);
        $service->generate(['code' => 'G1', 'type' => 'custom', 'value' => []]);
        $service->generate(['code' => 'G2', 'type' => 'custom', 'value' => []]);
        $service->redeem('G1', User::factory()->create());
        $service->redeem('G2', User::factory()->create());

        $this->getJson(self::BASE.'/coupon-redemptions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'coupon', 'redeemable' => ['type', 'id'], 'snapshot', 'redeemed_at']],
                'meta',
                'links',
            ]);
    }

    public function test_admin_can_filter_redemptions_by_coupon(): void
    {
        $this->actingAsAdmin();

        $service = $this->app->make(CouponService::class);
        $a = $service->generate(['code' => 'AA', 'type' => 'custom', 'value' => []]);
        $service->generate(['code' => 'BB', 'type' => 'custom', 'value' => []]);
        $service->redeem('AA', User::factory()->create());
        $service->redeem('BB', User::factory()->create());

        $this->getJson(self::BASE.'/coupon-redemptions?coupon_code_id='.$a->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.coupon.code', 'AA');
    }

    public function test_non_admin_cannot_access_redemptions(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['user']);

        $this->getJson(self::BASE.'/coupon-redemptions')->assertForbidden();
    }
}
