<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mrsuner\AdminCoupon\Models\CouponCode;
use Mrsuner\AdminCoupon\Services\CouponService;
use Mrsuner\AdminCoupon\Tests\Fixtures\User;
use Mrsuner\AdminCoupon\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/internal/admin/v1';

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['admin']);

        return $user;
    }

    private function makeCoupon(array $overrides = []): CouponCode
    {
        return $this->app->make(CouponService::class)->generate(array_merge([
            'type'  => 'percent_off',
            'value' => ['percent' => 10],
        ], $overrides));
    }

    public function test_admin_can_list_coupons(): void
    {
        $this->actingAsAdmin();
        $this->makeCoupon(['code' => 'A']);
        $this->makeCoupon(['code' => 'B']);

        $this->getJson(self::BASE.'/coupons')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_admin_can_filter_coupons_by_type(): void
    {
        $this->actingAsAdmin();
        $this->makeCoupon(['code' => 'P', 'type' => 'percent_off']);
        $this->makeCoupon(['code' => 'F', 'type' => 'free_months', 'value' => ['months' => 1]]);

        $this->getJson(self::BASE.'/coupons?type=free_months')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'F');
    }

    public function test_admin_can_filter_coupons_by_active_status(): void
    {
        $this->actingAsAdmin();
        $this->makeCoupon(['code' => 'ON', 'is_active' => true]);
        $this->makeCoupon(['code' => 'OFF', 'is_active' => false]);

        $this->getJson(self::BASE.'/coupons?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'OFF');
    }

    public function test_admin_can_create_coupon(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/coupons', [
            'code'         => 'LAUNCH20',
            'name'         => 'Launch promotion',
            'type'         => 'percent_off',
            'value'        => ['percent' => 20],
            'restrictions' => ['max_uses' => 500, 'per_user' => 1],
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LAUNCH20')
            ->assertJsonPath('data.type', 'percent_off');

        $this->assertDatabaseHas('coupon_codes', ['code' => 'LAUNCH20']);
    }

    public function test_admin_can_create_coupon_without_explicit_code(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::BASE.'/coupons', [
            'type'  => 'custom',
            'value' => ['key' => 'x'],
        ])->assertCreated();

        $code = $response->json('data.code');
        $this->assertNotEmpty($code);
        $this->assertDatabaseHas('coupon_codes', ['code' => $code]);
    }

    public function test_admin_can_bulk_generate_coupons(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/coupons/bulk', [
            'quantity' => 50,
            'prefix'   => 'LAUNCH',
            'type'     => 'free_months',
            'value'    => ['months' => 1],
        ])
            ->assertCreated()
            ->assertJsonCount(50, 'data');

        $this->assertSame(50, CouponCode::query()->count());
    }

    public function test_admin_can_view_coupon_detail(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'SHOW']);

        $this->getJson(self::BASE.'/coupons/'.$coupon->id)
            ->assertOk()
            ->assertJsonPath('data.code', 'SHOW')
            ->assertJsonPath('data.redemptions_count', 0);
    }

    public function test_admin_can_update_coupon_name_and_restrictions(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'UPD', 'name' => 'old']);

        $this->patchJson(self::BASE.'/coupons/'.$coupon->id, [
            'name'         => 'new name',
            'restrictions' => ['max_uses' => 99],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'new name')
            ->assertJsonPath('data.restrictions.max_uses', 99);
    }

    public function test_admin_cannot_update_coupon_type_or_value(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'IMM', 'type' => 'percent_off', 'value' => ['percent' => 10]]);

        $this->patchJson(self::BASE.'/coupons/'.$coupon->id, [
            'type'  => 'amount_off',
            'value' => ['amount' => 9999],
            'name'  => 'renamed',
        ])->assertOk();

        $coupon->refresh();
        $this->assertSame('percent_off', $coupon->type);
        $this->assertSame(['percent' => 10], $coupon->value);
    }

    public function test_admin_can_deactivate_coupon(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'DEA', 'is_active' => true]);

        $this->patchJson(self::BASE.'/coupons/'.$coupon->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_activate_coupon(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'ACT', 'is_active' => false]);

        $this->patchJson(self::BASE.'/coupons/'.$coupon->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_can_delete_unused_coupon(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'DEL']);

        $this->deleteJson(self::BASE.'/coupons/'.$coupon->id)->assertOk();

        $this->assertSoftDeleted('coupon_codes', ['id' => $coupon->id]);
    }

    public function test_admin_cannot_delete_redeemed_coupon_without_force(): void
    {
        $this->actingAsAdmin();
        $coupon = $this->makeCoupon(['code' => 'USED']);
        $coupon->update(['times_redeemed' => 3]);

        $this->deleteJson(self::BASE.'/coupons/'.$coupon->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('coupon_codes', ['id' => $coupon->id, 'deleted_at' => null]);
    }

    public function test_admin_can_force_delete_redeemed_coupon(): void
    {
        $this->actingAsAdmin();
        $user   = User::factory()->create();
        $coupon = $this->makeCoupon(['code' => 'FORCE']);
        $this->app->make(CouponService::class)->redeem('FORCE', $user);

        $this->deleteJson(self::BASE.'/coupons/'.$coupon->id.'?force=true')->assertOk();

        $this->assertSoftDeleted('coupon_codes', ['id' => $coupon->id]);
        // Redemption history is preserved.
        $this->assertDatabaseCount('coupon_redemptions', 1);
    }

    public function test_admin_can_list_coupon_redemptions_for_a_coupon(): void
    {
        $this->actingAsAdmin();
        $user   = User::factory()->create();
        $coupon = $this->makeCoupon(['code' => 'RDM']);
        $this->app->make(CouponService::class)->redeem('RDM', $user);

        $this->getJson(self::BASE.'/coupons/'.$coupon->id.'/redemptions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_non_admin_cannot_access_coupon_endpoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['user']);

        $this->getJson(self::BASE.'/coupons')->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson(self::BASE.'/coupons')->assertUnauthorized();
    }

    public function test_request_outside_ip_whitelist_is_rejected(): void
    {
        config()->set('boilerplate.admin.ip_whitelist.enabled', true);
        config()->set('boilerplate.admin.ip_whitelist.cidrs', ['100.64.0.0/10']);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson(self::BASE.'/coupons')
            ->assertForbidden();
    }

    #[DefineEnvironment('disableAdminModule')]
    public function test_routes_return_404_when_admin_module_disabled(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/coupons')->assertNotFound();
    }

    protected function disableAdminModule($app): void
    {
        $app['config']->set('boilerplate.admin.enabled', false);
    }
}
