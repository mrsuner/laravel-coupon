<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mrsuner\AdminCoupon\Http\Controllers\CouponAdminController;
use Mrsuner\AdminCoupon\Http\Controllers\CouponRedemptionAdminController;

/*
| Mounted by CouponServiceProvider under the configured prefix
| (default: internal/admin/v1) and the boilerplate admin middleware stack.
| The group also applies the "admin." route-name prefix.
*/

Route::get('coupons', [CouponAdminController::class, 'index'])->name('coupons.index');
Route::post('coupons', [CouponAdminController::class, 'store'])->name('coupons.store');

// Must be declared before the {coupon} routes so "bulk" is not treated as an id.
Route::post('coupons/bulk', [CouponAdminController::class, 'bulk'])->name('coupons.bulk');

Route::get('coupons/{coupon}', [CouponAdminController::class, 'show'])->name('coupons.show');
Route::patch('coupons/{coupon}', [CouponAdminController::class, 'update'])->name('coupons.update');
Route::delete('coupons/{coupon}', [CouponAdminController::class, 'destroy'])->name('coupons.destroy');

Route::patch('coupons/{coupon}/activate', [CouponAdminController::class, 'activate'])->name('coupons.activate');
Route::patch('coupons/{coupon}/deactivate', [CouponAdminController::class, 'deactivate'])->name('coupons.deactivate');

Route::get('coupons/{coupon}/redemptions', [CouponAdminController::class, 'redemptions'])->name('coupons.redemptions');

Route::get('coupon-redemptions', [CouponRedemptionAdminController::class, 'index'])->name('coupon-redemptions.index');
