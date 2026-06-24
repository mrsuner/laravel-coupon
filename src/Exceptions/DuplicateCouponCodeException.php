<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by CouponService::generate() when a custom code already exists.
 */
final class DuplicateCouponCodeException extends InvalidArgumentException
{
    public static function forCode(string $code): self
    {
        return new self("Coupon code [{$code}] already exists.");
    }
}
