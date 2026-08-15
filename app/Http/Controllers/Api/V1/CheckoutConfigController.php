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
        return $this->success([
            'currency' => 'SYP',
            'payment_methods' => HomepageSetting::publicPaymentMethods(),
        ]);
    }
}
