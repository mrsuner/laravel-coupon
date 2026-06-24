<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $coupon_code_id
 * @property string|null $redeemable_type
 * @property int|string|null $redeemable_id
 * @property array $snapshot
 * @property array|null $context
 * @property \Illuminate\Support\Carbon $redeemed_at
 */
class CouponRedemption extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot'    => 'array',
        'context'     => 'array',
        'redeemed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('admin-coupon.table_names.coupon_redemptions', 'coupon_redemptions');
    }

    /**
     * @return BelongsTo<CouponCode, CouponRedemption>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CouponCode::class, 'coupon_code_id');
    }

    public function redeemable(): MorphTo
    {
        return $this->morphTo();
    }
}
