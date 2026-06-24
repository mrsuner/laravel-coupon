<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mrsuner\AdminCoupon\Http\Requests\BulkCouponRequest;
use Mrsuner\AdminCoupon\Http\Requests\StoreCouponRequest;
use Mrsuner\AdminCoupon\Http\Requests\UpdateCouponRequest;
use Mrsuner\AdminCoupon\Http\Resources\CouponCodeResource;
use Mrsuner\AdminCoupon\Http\Resources\CouponRedemptionResource;
use Mrsuner\AdminCoupon\Models\CouponCode;
use Mrsuner\AdminCoupon\Services\CouponService;

class CouponAdminController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $coupons = CouponCode::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search');
                $q->where(function ($inner) use ($search): void {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->through(fn (CouponCode $coupon) => (new CouponCodeResource($coupon))->resolve());

        return $this->respondPaginated($coupons);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = $this->coupons->generate($request->validated());

        $this->audit('admin.coupon.created', $coupon);

        return $this->respondCreated(CouponCodeResource::make($coupon));
    }

    public function bulk(BulkCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $coupons = $this->coupons->generateBulk((int) $validated['quantity'], [
            'code'         => $validated['prefix'] ?? null,
            'name'         => $validated['name'] ?? null,
            'type'         => $validated['type'],
            'value'        => $validated['value'],
            'restrictions' => $validated['restrictions'] ?? null,
        ]);

        $this->audit('admin.coupon.bulk_created', null, [
            'metadata' => ['quantity' => $coupons->count()],
        ]);

        return $this->respondCreated($coupons->pluck('code')->all());
    }

    public function show(CouponCode $coupon): JsonResponse
    {
        $coupon->loadCount('redemptions');

        return $this->respondOk(CouponCodeResource::make($coupon));
    }

    public function update(UpdateCouponRequest $request, CouponCode $coupon): JsonResponse
    {
        // type and value are intentionally not fillable here — immutable.
        $coupon->fill($request->validated());
        $coupon->save();

        $this->audit('admin.coupon.updated', $coupon);

        return $this->respondOk(CouponCodeResource::make($coupon->refresh()));
    }

    public function destroy(Request $request, CouponCode $coupon): JsonResponse
    {
        if ($coupon->times_redeemed > 0 && ! $request->boolean('force')) {
            return $this->respondError(
                422,
                'Coupon has redemptions. Pass force=true to confirm deletion.',
            );
        }

        $coupon->delete();

        $this->audit('admin.coupon.deleted', $coupon);

        return $this->respondOk(message: 'Coupon deleted.');
    }

    public function activate(CouponCode $coupon): JsonResponse
    {
        $coupon->is_active = true;
        $coupon->save();

        $this->audit('admin.coupon.activated', $coupon);

        return $this->respondOk(CouponCodeResource::make($coupon));
    }

    public function deactivate(CouponCode $coupon): JsonResponse
    {
        $coupon->is_active = false;
        $coupon->save();

        $this->audit('admin.coupon.deactivated', $coupon);

        return $this->respondOk(CouponCodeResource::make($coupon));
    }

    public function redemptions(Request $request, CouponCode $coupon): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $redemptions = $coupon->redemptions()
            ->latest('redeemed_at')
            ->paginate($perPage)
            ->through(fn ($redemption) => (new CouponRedemptionResource($redemption))->resolve());

        return $this->respondPaginated($redemptions);
    }
}
