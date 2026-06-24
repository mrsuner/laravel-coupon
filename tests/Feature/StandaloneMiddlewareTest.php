<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mrsuner\Coupon\Tests\Fixtures\User;
use Mrsuner\Coupon\Tests\TestCase;

/**
 * Exercises the auto-detected admin middleware stack in a plain Laravel app
 * (no boilerplate InternalIpWhitelist present), where it should fall back to
 * ['auth:sanctum'] with no admin-ability requirement.
 */
class StandaloneMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/internal/admin/v1';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // null => let the service provider auto-detect the stack.
        $app['config']->set('coupon.route.middleware', null);
    }

    public function test_any_authenticated_token_is_allowed(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['no-special-abilities']);

        $this->getJson(self::BASE.'/coupons')->assertOk();
    }

    public function test_unauthenticated_request_is_still_rejected(): void
    {
        $this->getJson(self::BASE.'/coupons')->assertUnauthorized();
    }
}
