<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mrsuner\Coupon\Models\CouponRedemption;

/**
 * @mixin CouponRedemption
 */
class CouponRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'coupon'     => CouponCodeResource::make($this->whenLoaded('coupon')),
            'redeemable' => [
                'type' => $this->redeemable_type,
                'id'   => $this->redeemable_id,
            ],
            'snapshot'    => $this->snapshot,
            'context'     => $this->context,
            'redeemed_at' => $this->redeemed_at->toIso8601String(),
        ];
    }
}
