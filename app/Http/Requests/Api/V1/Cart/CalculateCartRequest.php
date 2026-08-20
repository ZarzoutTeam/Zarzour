<?php

namespace App\Http\Requests\Api\V1\Cart;

use App\Rules\ActiveProvinceExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $currency = $this->input('currency');

        $normalized = [
            'coupon_code' => is_string($couponCode) ? strtoupper(trim($couponCode)) : $couponCode,
            'phone_number' => is_string($phoneNumber) ? trim($phoneNumber) : $phoneNumber,
        ];

        if ($this->has('currency')) {
            $normalized['currency'] = is_string($currency) ? strtoupper(trim($currency)) : $currency;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['bail', 'required', 'array', 'min:1'],
            'lines.*.product_id' => ['bail', 'required', 'integer', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['bail', 'required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['bail', 'nullable', 'string', 'regex:/^09[0-9]{8}$/'],
            'province_id' => ['bail', 'nullable', 'integer', new ActiveProvinceExists],
            'currency' => ['bail', 'sometimes', 'string', Rule::in(['SYP', 'USD'])],
        ];
    }
}
