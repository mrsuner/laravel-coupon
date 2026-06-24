<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Code auto-generation settings
    |--------------------------------------------------------------------------
    |
    | Used by CouponService::generate() / generateBulk() when no explicit code
    | is provided. The charset deliberately omits ambiguous characters.
    |
    */
    'generation' => [
        'length'  => (int) env('COUPON_CODE_LENGTH', 8),
        'prefix'  => (string) env('COUPON_CODE_PREFIX', ''),
        'suffix'  => (string) env('COUPON_CODE_SUFFIX', ''),
        // Character pool for random generation — no O,0,I,1 (ambiguous).
        'charset' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | Override if your application has conflicting table names.
    |
    */
    'table_names' => [
        'coupon_codes'       => 'coupon_codes',
        'coupon_redemptions' => 'coupon_redemptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin route wiring
    |--------------------------------------------------------------------------
    |
    | The package mounts its admin endpoints under this prefix and middleware
    | stack.
    |
    | enabled:    Master switch for the admin API. When false, no admin routes
    |             are registered (every admin endpoint 404s). If the host also
    |             defines `boilerplate.admin.enabled` and sets it to false, the
    |             routes are likewise skipped.
    |
    | middleware: null = auto-detect. When the boilerplate's
    |             App\Http\Middleware\InternalIpWhitelist class is present, the
    |             full boilerplate admin stack is applied; otherwise the package
    |             falls back to ['auth:sanctum', 'ability:admin'] so it works in
    |             any Laravel app with an admin-scoped Sanctum token. Provide an
    |             explicit array to take full control.
    |
    */
    'route' => [
        'enabled'    => true,
        'prefix'     => 'internal/admin/v1',
        'name'       => 'admin.',
        'middleware' => null,
    ],
];
