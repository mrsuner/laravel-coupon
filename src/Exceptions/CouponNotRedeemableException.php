<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Exceptions;

use Mrsuner\Coupon\ValueObjects\ValidationResult;
use RuntimeException;

/**
 * Thrown by CouponService::redeem() when validation fails.
 *
 * The message is one of the ValidationResult::* error constants, suitable for
 * use as a translation key (e.g. __('coupon.'.$e->getMessage())).
 */
final class CouponNotRedeemableException extends RuntimeException
{
    public function __construct(private readonly ValidationResult $result)
    {
        parent::__construct((string) $result->error);
    }

    public function getValidationResult(): ValidationResult
    {
        return $this->result;
    }
}
