<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Models\HomepageSetting;
use App\Rules\ActiveProvinceExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['bail', 'required', 'string', 'regex:/^09[0-9]{8}$/'],
            'province_id' => ['bail', 'required', 'integer', new ActiveProvinceExists],
            'shipping_address' => ['required', 'string'],
            'extra_notes' => ['nullable', 'string'],
            'payment_method' => ['bail', 'required', Rule::in(HomepageSetting::enabledPaymentMethodKeys())],
            'currency' => ['bail', 'sometimes', 'string', Rule::in(['SYP', 'USD'])],
            'coupon_code' => ['nullable', 'string', 'max:255'],
            'lines' => ['bail', 'required', 'array', 'min:1'],
            'lines.*.product_id' => ['bail', 'required', 'integer', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['bail', 'required', 'integer', 'min:1'],
        ];
    }
}
