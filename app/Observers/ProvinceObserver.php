<?php

namespace App\Observers;

use App\Models\Province;
use App\Services\ExchangeRateService;
use App\Support\CatalogCache;

class ProvinceObserver
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function saving(Province $province): void
    {
        if ($province->shipping_fee_usd === null) {
            return;
        }

        $converted = $this->exchangeRateService->convertUsdToSyp($province->shipping_fee_usd);

        if ($converted !== null) {
            $province->shipping_fee = $converted;
        }
    }

    public function saved(Province $province): void
    {
        CatalogCache::forgetProvinces();
    }

    public function deleted(Province $province): void
    {
        CatalogCache::forgetProvinces();
    }
}
