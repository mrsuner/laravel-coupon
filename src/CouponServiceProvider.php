<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Mrsuner\AdminCoupon\Services\CouponService;

class CouponServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-coupon.php', 'admin-coupon');

        $this->app->singleton(CouponService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/admin-coupon.php' => config_path('admin-coupon.php'),
        ], 'admin-coupon-config');

        $this->registerMiddlewareAliases();
        $this->registerRoutes();
    }

    /**
     * Ensure the `ability` middleware alias resolves to Sanctum's ability
     * check when Sanctum is installed and the host hasn't already aliased it.
     */
    private function registerMiddlewareAliases(): void
    {
        if (! class_exists(CheckForAnyAbility::class)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];

        if (! array_key_exists('ability', $router->getMiddleware())) {
            $router->aliasMiddleware('ability', CheckForAnyAbility::class);
        }
    }

    private function registerRoutes(): void
    {
        // Only mount routes if the boilerplate admin module is enabled.
        if (! config('boilerplate.admin.enabled', true)) {
            return;
        }

        // Ensure implicit route-model binding works regardless of how the host
        // wires the admin middleware stack (the configured stack may not include
        // the framework's binding substitution middleware).
        $middleware = array_merge(
            (array) config('admin-coupon.route.middleware', []),
            [SubstituteBindings::class],
        );

        Route::prefix(config('admin-coupon.route.prefix', 'internal/admin/v1'))
            ->middleware($middleware)
            ->name(config('admin-coupon.route.name', 'admin.'))
            ->group(__DIR__.'/../routes/admin.php');
    }
}
