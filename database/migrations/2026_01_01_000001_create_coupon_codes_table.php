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

            // The redeemable code string.
            $table->string('code', 64)->unique();

            // Human-readable name for admin display.
            $table->string('name')->nullable();

            /*
             * type: what kind of effect this coupon represents. The package does
             * not enforce the effect — it is metadata for the host app.
             *
             * Suggested values (extensible by host app):
             *   percent_off — value: { "percent": 20 }
             *   amount_off  — value: { "amount": 500, "currency": "USD" }
             *   free_months — value: { "months": 1 }
             *   custom      — value: { "key": "anything" }
             */
            $table->string('type');
            $table->json('value');

            /*
             * restrictions: usage constraints evaluated during validation.
             * All keys are optional. Null = no restriction on that dimension.
             *
             *   max_uses   — total redemptions allowed across all users
             *   per_user   — max redemptions per unique redeemable
             *   expires_at — ISO 8601 datetime after which the code is invalid
             *   min_amount — minimum transaction value in cents
             */
            $table->json('restrictions')->nullable();

            // Running count of successful redemptions.
            $table->unsignedInteger('times_redeemed')->default(0);

            // Soft on/off toggle for admin — does not delete redemption history.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('coupon.table_names.coupon_codes', 'coupon_codes');
    }
};
