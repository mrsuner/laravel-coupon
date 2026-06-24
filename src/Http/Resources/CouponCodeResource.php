<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Mrsuner\AdminCoupon\Models\CouponCode;

/**
 * @mixin CouponCode
 */
class CouponCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'type'              => $this->type,
            'value'             => $this->value,
            'restrictions'      => $this->restrictions,
            'times_redeemed'    => $this->times_redeemed,
            'is_active'         => $this->is_active,
            'is_expired'        => $this->isExpired(),
            'is_exhausted'      => $this->isExhausted(),
            'created_at'        => $this->created_at->toIso8601String(),
            'updated_at'        => $this->updated_at->toIso8601String(),
            'redemptions_count' => $this->whenCounted('redemptions'),
        ];
    }
}
