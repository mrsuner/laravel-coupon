<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Mrsuner\Coupon\Models\CouponCode;
use Mrsuner\Coupon\Models\CouponRedemption;

/**
 * Fired after a successful redemption has been committed.
 *
 * This is the single seam between the package and the host application's
 * business logic. The package fires this event and stops; the host app
 * listens and acts (billing, credits, trial extension, etc.).
 */
final class CouponRedeemed
{
    use Dispatchable;

    public function __construct(
        public readonly CouponCode $coupon,
        public readonly Model $redeemable,
        public readonly CouponRedemption $redemption,
    ) {}
}
