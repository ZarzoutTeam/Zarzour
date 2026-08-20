<?php

namespace App\Observers;

use App\Models\Coupon;
use App\Services\ExchangeRateService;

class CouponObserver
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function saving(Coupon $coupon): void
    {
        if ($coupon->type === 'fixed') {
            if ($coupon->value_usd !== null) {
                $converted = $this->exchangeRateService->convertUsdToSyp($coupon->value_usd);

                if ($converted !== null) {
                    $coupon->value = $converted;
                }
            }
        } else {
            $coupon->value_usd = null;
        }

        $this->convertWhenPresent($coupon, 'max_discount_amount_usd', 'max_discount_amount');
        $this->convertWhenPresent($coupon, 'min_order_amount_usd', 'min_order_amount');
    }

    private function convertWhenPresent(Coupon $coupon, string $usdColumn, string $sypColumn): void
    {
        if ($coupon->{$usdColumn} !== null) {
            $converted = $this->exchangeRateService->convertUsdToSyp($coupon->{$usdColumn});

            if ($converted !== null) {
                $coupon->{$sypColumn} = $converted;
            }

            return;
        }

        if ($coupon->exists && $coupon->isDirty($usdColumn)) {
            $coupon->{$sypColumn} = null;
        }
    }
}
