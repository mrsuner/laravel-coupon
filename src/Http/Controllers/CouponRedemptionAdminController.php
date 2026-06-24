<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mrsuner\Coupon\Http\Resources\CouponRedemptionResource;
use Mrsuner\Coupon\Models\CouponRedemption;

class CouponRedemptionAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $redemptions = CouponRedemption::query()
            ->with('coupon')
            ->when($request->filled('coupon_code_id'), fn ($q) => $q->where('coupon_code_id', $request->integer('coupon_code_id')))
            ->when($request->filled('redeemable_type'), fn ($q) => $q->where('redeemable_type', $request->string('redeemable_type')))
            ->when($request->filled('redeemable_id'), fn ($q) => $q->where('redeemable_id', $request->string('redeemable_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('redeemed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('redeemed_at', '<=', $request->date('to')))
            ->latest('redeemed_at')
            ->paginate($perPage)
            ->through(fn (CouponRedemption $redemption) => (new CouponRedemptionResource($redemption))->resolve());

        return $this->respondPaginated($redemptions);
    }
}
