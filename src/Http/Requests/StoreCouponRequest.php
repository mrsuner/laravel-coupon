<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = config('admin-coupon.table_names.coupon_codes', 'coupon_codes');

        return [
            'code'         => ['nullable', 'string', 'max:64', Rule::unique($table, 'code')],
            'name'         => ['nullable', 'string', 'max:255'],
            'type'         => ['required', 'string', 'max:255'],
            'value'        => ['required', 'array'],
            'restrictions' => ['nullable', 'array'],

            'restrictions.max_uses'   => ['nullable', 'integer', 'min:1'],
            'restrictions.per_user'   => ['nullable', 'integer', 'min:1'],
            'restrictions.expires_at' => ['nullable', 'date'],
            'restrictions.min_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
