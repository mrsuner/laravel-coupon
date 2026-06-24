<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Tests\Unit;

use Mrsuner\Coupon\Models\CouponCode;
use Mrsuner\Coupon\Tests\TestCase;
use Mrsuner\Coupon\ValueObjects\ValidationResult;

class ValidationResultTest extends TestCase
{
    public function test_valid_result_carries_coupon_and_no_error(): void
    {
        $coupon = new CouponCode(['code' => 'ABC', 'type' => 'custom', 'value' => []]);

        $result = ValidationResult::valid($coupon);

        $this->assertTrue($result->valid);
        $this->assertSame($coupon, $result->coupon);
        $this->assertNull($result->error);
    }

    public function test_invalid_result_carries_error_and_optional_coupon(): void
    {
        $result = ValidationResult::invalid(ValidationResult::NOT_FOUND);

        $this->assertFalse($result->valid);
        $this->assertNull($result->coupon);
        $this->assertSame('not_found', $result->error);
    }

    public function test_error_constants_have_expected_values(): void
    {
        $this->assertSame('not_found', ValidationResult::NOT_FOUND);
        $this->assertSame('inactive', ValidationResult::INACTIVE);
        $this->assertSame('expired', ValidationResult::EXPIRED);
        $this->assertSame('exhausted', ValidationResult::EXHAUSTED);
        $this->assertSame('user_limit_reached', ValidationResult::USER_LIMIT);
        $this->assertSame('min_amount_not_met', ValidationResult::MIN_AMOUNT);
    }
}
