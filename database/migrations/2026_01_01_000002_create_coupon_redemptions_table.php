<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_code_id')
                ->constrained($this->couponCodesTable())
                ->cascadeOnDelete();

            /*
             * Polymorphic redeemable: who or what redeemed this coupon.
             * Typically App\Models\User, but could be App\Models\Team or any
             * other model. The host application passes the redeemable instance
             * to CouponService::redeem().
             */
            $table->nullableMorphs('redeemable');

            /*
             * Snapshot of coupon type + value at redemption time. Ensures
             * historical accuracy if the coupon is later edited.
             */
            $table->json('snapshot');

            // Optional metadata from the host app (e.g. order_id, subscription_id).
            $table->json('context')->nullable();

            $table->timestamp('redeemed_at')->useCurrent();

            $table->index('coupon_code_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('admin-coupon.table_names.coupon_redemptions', 'coupon_redemptions');
    }

    private function couponCodesTable(): string
    {
        return config('admin-coupon.table_names.coupon_codes', 'coupon_codes');
    }
};
