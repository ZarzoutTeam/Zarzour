<?php

namespace App\Observers;

use App\Models\HomepageSetting;
use App\Services\ExchangeRateService;
use App\Support\CatalogCache;

class HomepageSettingObserver
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function saved(HomepageSetting $homepageSetting): void
    {
        if (
            $homepageSetting->usd_to_syp_rate !== null
            && ($homepageSetting->wasRecentlyCreated || $homepageSetting->wasChanged('usd_to_syp_rate'))
        ) {
            $this->exchangeRateService->syncProductPrices((float) $homepageSetting->usd_to_syp_rate);
            CatalogCache::forgetBanners();
        }

        CatalogCache::forgetHome();
    }

    public function deleted(HomepageSetting $homepageSetting): void
    {
        CatalogCache::forgetHome();
    }
}
