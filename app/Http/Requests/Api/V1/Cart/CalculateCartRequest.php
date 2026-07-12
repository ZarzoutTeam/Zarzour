<?php

namespace App\Http\Requests\Api\V1\Cart;

use App\Rules\ActiveProvinceExists;
use Illuminate\Foundation\Http\FormRequest;

class CalculateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string'],
            'province_id' => ['nullable', 'integer', new ActiveProvinceExists],
        ];
    }
}
