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

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^09[0-9]{8}$/'],
            'province_id' => ['required', 'integer', new ActiveProvinceExists],
            'shipping_address' => ['required', 'string'],
            'extra_notes' => ['nullable', 'string'],
            'payment_method' => ['required', Rule::in(HomepageSetting::enabledPaymentMethodKeys())],
            'coupon_code' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
