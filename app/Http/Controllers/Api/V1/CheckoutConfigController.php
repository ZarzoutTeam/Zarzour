<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutConfigController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        $settings = HomepageSetting::query()->first();

        return $this->success([
            'currency' => 'SYP',
            'exchange_rate' => $settings?->exchangeRatePayload(),
            'payment_methods' => $settings?->paymentMethodsPayload()
                ?? HomepageSetting::publicPaymentMethods(),
        ]);
    }
}
