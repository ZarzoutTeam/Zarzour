<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cart\CalculateCartRequest;
use App\Services\PriceCalculationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PriceCalculationService $priceCalculationService,
    ) {}

    /**
     * Read-only pricing preview: direct discount -> coupon -> offer/gift -> shipping.
     * Never touches stock and never increments coupon usage.
     */
    public function calculate(CalculateCartRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->priceCalculationService->calculate(
            $data['lines'],
            $data['coupon_code'] ?? null,
            $data['phone_number'] ?? null,
            $data['province_id'] ?? null,
        );

        return $this->success($result);
    }
}
