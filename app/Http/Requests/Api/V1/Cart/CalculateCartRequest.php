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

    protected function prepareForValidation(): void
    {
        $couponCode = $this->input('coupon_code');
        $phoneNumber = $this->input('phone_number');

        $this->merge([
            'coupon_code' => is_string($couponCode) ? strtoupper(trim($couponCode)) : $couponCode,
            'phone_number' => is_string($phoneNumber) ? trim($phoneNumber) : $phoneNumber,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'regex:/^09[0-9]{8}$/'],
            'province_id' => ['nullable', 'integer', new ActiveProvinceExists],
        ];
    }
}
