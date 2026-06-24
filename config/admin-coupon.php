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
    | Override if the host application has conflicting table names.
    |
    */
    'table_names' => [
        'coupon_codes'       => 'coupon_codes',
        'coupon_redemptions' => 'coupon_redemptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redeemable morph map alias
    |--------------------------------------------------------------------------
    |
    | If the host app uses morph maps, set this to the registered alias for the
    | redeemable model (e.g. User). Null = uses the fully-qualified class name
    | (default Laravel behaviour).
    |
    */
    'redeemable_morph_map' => null,

    /*
    |--------------------------------------------------------------------------
    | Admin route wiring
    |--------------------------------------------------------------------------
    |
    | The package mounts its admin endpoints under this prefix and middleware
    | stack. The defaults match the boilerplate admin module. Override the
    | middleware here if your host app uses a different admin stack — this is
    | also what the package's own test suite overrides.
    |
    */
    'route' => [
        'prefix'     => 'internal/admin/v1',
        'name'       => 'admin.',
        'middleware' => [
            'throttle:60,1',
            'App\\Http\\Middleware\\InternalIpWhitelist',
            'auth:sanctum',
            'ability:admin',
        ],
    ],
];
