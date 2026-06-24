<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * Minimal redeemable model used by the package test suite.
 */
class User extends Authenticatable implements HasApiTokensContract
{
    use HasApiTokens;
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
