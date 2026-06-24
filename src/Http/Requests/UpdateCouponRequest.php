<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only name, is_active, and restrictions are mutable. type and value are
 * immutable after creation to preserve redemption snapshot integrity.
 */
class UpdateCouponRequest extends FormRequest
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
        return [
            'name'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active'    => ['sometimes', 'boolean'],
            'restrictions' => ['sometimes', 'nullable', 'array'],

            'restrictions.max_uses'   => ['nullable', 'integer', 'min:1'],
            'restrictions.per_user'   => ['nullable', 'integer', 'min:1'],
            'restrictions.expires_at' => ['nullable', 'date'],
            'restrictions.min_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
