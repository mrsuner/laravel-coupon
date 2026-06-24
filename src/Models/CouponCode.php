<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property string $type
 * @property array $value
 * @property array|null $restrictions
 * @property int $times_redeemed
 * @property bool $is_active
 */
class CouponCode extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'value'          => 'array',
        'restrictions'   => 'array',
        'times_redeemed' => 'integer',
        'is_active'      => 'boolean',
    ];

    public function getTable(): string
    {
        return config('coupon.table_names.coupon_codes', 'coupon_codes');
    }

    /**
     * @return HasMany<CouponRedemption>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_code_id');
    }

    /**
     * Active and not expired.
     *
     * @param  Builder<CouponCode>  $query
     * @return Builder<CouponCode>
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('restrictions->expires_at')
                    ->orWhere('restrictions->expires_at', '>', now()->toIso8601String());
            });
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->restrictions['expires_at'] ?? null;

        if ($expiresAt === null) {
            return false;
        }

        return now()->greaterThan($expiresAt);
    }

    /**
     * times_redeemed >= restrictions.max_uses.
     */
    public function isExhausted(): bool
    {
        $maxUses = $this->restrictions['max_uses'] ?? null;

        if ($maxUses === null) {
            return false;
        }

        return $this->times_redeemed >= (int) $maxUses;
    }

    /**
     * Whether the given redeemable is still under the per_user limit.
     */
    public function isUsableBy(Model $redeemable): bool
    {
        $perUser = $this->restrictions['per_user'] ?? null;

        if ($perUser === null) {
            return true;
        }

        $used = $this->redemptions()
            ->where('redeemable_type', $redeemable->getMorphClass())
            ->where('redeemable_id', $redeemable->getKey())
            ->count();

        return $used < (int) $perUser;
    }

    /**
     * Combines all checks: active, not expired, not exhausted, and (when a
     * redeemable is supplied) under the per-user limit.
     */
    public function isRedeemable(?Model $redeemable = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isExpired() || $this->isExhausted()) {
            return false;
        }

        if ($redeemable !== null && ! $this->isUsableBy($redeemable)) {
            return false;
        }

        return true;
    }
}
