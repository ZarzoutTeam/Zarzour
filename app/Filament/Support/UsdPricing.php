<?php

namespace App\Filament\Support;

use App\Rules\ExchangeRateConfigured;
use App\Services\ExchangeRateService;
use Illuminate\Contracts\Validation\ValidationRule;

final class UsdPricing
{
    public static function convertToSyp(mixed $usdAmount): ?float
    {
        return app(ExchangeRateService::class)->convertUsdToSyp($usdAmount);
    }

    public static function sypHelperText(): string
    {
        $rate = app(ExchangeRateService::class)->currentUsdToSypRate();

        return $rate === null
            ? 'لم يُحدد سعر الصرف بعد. انتقل إلى لوحة التحكم الرئيسية وحدده أولاً.'
            : 'محسوب تلقائياً بسعر صرف '.number_format($rate, 2).' ل.س لكل دولار.';
    }

    public static function exchangeRateConfiguredRule(): ValidationRule
    {
        return new ExchangeRateConfigured;
    }
}
