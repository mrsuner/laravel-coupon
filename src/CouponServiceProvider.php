<?php

declare(strict_types=1);

namespace Mrsuner\Coupon;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Mrsuner\Coupon\Services\CouponService;

class CouponServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/coupon.php', 'coupon');

        $this->app->singleton(CouponService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/coupon.php' => config_path('coupon.php'),
        ], 'coupon-config');

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
        // Double gate: the package's own switch, plus the boilerplate admin
        // master switch when the host application defines it.
        if (! config('coupon.route.enabled', true)) {
            return;
        }

        if (! config('boilerplate.admin.enabled', true)) {
            return;
        }

        // Ensure implicit route-model binding works regardless of how the
        // admin middleware stack is wired (a custom stack may not include the
        // framework's binding substitution middleware).
        $middleware = array_merge(
            $this->resolveRouteMiddleware(),
            [SubstituteBindings::class],
        );

        Route::prefix(config('coupon.route.prefix', 'internal/admin/v1'))
            ->middleware($middleware)
            ->name(config('coupon.route.name', 'admin.'))
            ->group(__DIR__.'/../routes/admin.php');
    }

    /**
     * Resolve the admin middleware stack.
     *
     * An explicit `coupon.route.middleware` array always wins. When it is null
     * (the default), the stack is auto-detected: the full boilerplate admin
     * stack when its IP-whitelist middleware is present, otherwise a minimal
     * Sanctum-authenticated stack so the package works in any Laravel app.
     *
     * @return array<int, string>
     */
    private function resolveRouteMiddleware(): array
    {
        $configured = config('coupon.route.middleware');

        if (is_array($configured)) {
            return $configured;
        }

        $boilerplateIpWhitelist = 'App\\Http\\Middleware\\InternalIpWhitelist';
        $boilerplateEnsureAdminAccess = 'App\\Http\\Middleware\\EnsureAdminAccess';

        if (class_exists($boilerplateIpWhitelist)) {
            $middleware = [
                'throttle:60,1',
                $boilerplateIpWhitelist,
                'auth:sanctum',
                'ability:admin',
            ];

            if (class_exists($boilerplateEnsureAdminAccess)) {
                $middleware[] = $boilerplateEnsureAdminAccess;
            }

            return $middleware;
        }

        return ['auth:sanctum'];
    }
}
