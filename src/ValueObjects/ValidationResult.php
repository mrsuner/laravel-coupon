<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\ValueObjects;

use Mrsuner\Coupon\Models\CouponCode;

/**
 * Immutable result of a coupon validation check.
 *
 * Returned by CouponService::validate() and carried by
 * CouponNotRedeemableException when redemption fails.
 */
final class ValidationResult
{
    public const NOT_FOUND  = 'not_found';
    public const INACTIVE   = 'inactive';
    public const EXPIRED    = 'expired';
    public const EXHAUSTED  = 'exhausted';
    public const USER_LIMIT = 'user_limit_reached';
    public const MIN_AMOUNT = 'min_amount_not_met';

    private function __construct(
        public readonly bool $valid,
        public readonly ?CouponCode $coupon = null,
        public readonly ?string $error = null,
    ) {}

    public static function valid(CouponCode $coupon): self
    {
        return new self(valid: true, coupon: $coupon, error: null);
    }

    public static function invalid(string $error, ?CouponCode $coupon = null): self
    {
        return new self(valid: false, coupon: $coupon, error: $error);
    }
}
