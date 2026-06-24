<?php

declare(strict_types=1);

namespace Mrsuner\AdminCoupon\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCouponRequest extends FormRequest
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
            'quantity'     => ['required', 'integer', 'min:1', 'max:1000'],
            'prefix'       => ['nullable', 'string', 'max:32'],
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
