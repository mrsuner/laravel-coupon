<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Tests;

use Laravel\Sanctum\SanctumServiceProvider;
use Mrsuner\AdminCoupon\CouponServiceProvider;
use Mrsuner\AdminCoupon\Tests\Fixtures\IpWhitelistStub;
use Mrsuner\AdminCoupon\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            CouponServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Sanctum auth wired to the test User model.
        $config->set('auth.guards.sanctum', ['driver' => 'sanctum', 'provider' => 'users']);
        $config->set('auth.providers.users.driver', 'eloquent');
        $config->set('auth.providers.users.model', User::class);

        // Admin module relies on the config default (true). It is intentionally
        // left unset here so a per-test DefineEnvironment can disable it without
        // an ordering conflict. IP whitelist is off by default (opted into per-test).
        $config->set('boilerplate.admin.ip_whitelist.enabled', false);
        $config->set('boilerplate.admin.ip_whitelist.cidrs', ['100.64.0.0/10']);

        // Test route stack: IP stub + sanctum auth + admin ability (no throttle).
        $config->set('admin-coupon.route.middleware', [
            IpWhitelistStub::class,
            'auth:sanctum',
            'ability:admin',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Test users table; package + sanctum migrations are auto-loaded
        // by their respective service providers.
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
