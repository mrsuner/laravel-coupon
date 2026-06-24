<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Tests\Unit;

use Mrsuner\Coupon\CouponServiceProvider;
use Mrsuner\Coupon\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionMethod;

class CouponServiceProviderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_boilerplate_stack_includes_live_admin_access_when_available(): void
    {
        eval('namespace App\\Http\\Middleware; class InternalIpWhitelist {}');
        eval('namespace App\\Http\\Middleware; class EnsureAdminAccess {}');

        config()->set('coupon.route.middleware', null);

        $method = new ReflectionMethod(CouponServiceProvider::class, 'resolveRouteMiddleware');
        $method->setAccessible(true);

        $middleware = $method->invoke(new CouponServiceProvider($this->app));

        $this->assertSame([
            'throttle:60,1',
            'App\\Http\\Middleware\\InternalIpWhitelist',
            'auth:sanctum',
            'ability:admin',
            'App\\Http\\Middleware\\EnsureAdminAccess',
        ], $middleware);
    }
}
