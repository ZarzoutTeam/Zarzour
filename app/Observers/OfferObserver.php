<?php

namespace App\Observers;

use App\Exceptions\OverlappingOfferException;
use App\Models\Offer;
use App\Services\ExchangeRateService;
use App\Support\CatalogCache;

class OfferObserver
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function saving(Offer $offer): void
    {
        if (! $offer->hasDiscount()) {
            $offer->discount_type = null;
            $offer->discount_value = null;
            $offer->discount_value_usd = null;
        } elseif ($offer->discount_type === 'fixed' && $offer->discount_value_usd !== null) {
            $converted = $this->exchangeRateService->convertUsdToSyp($offer->discount_value_usd);

            if ($converted !== null) {
                $offer->discount_value = $converted;
            }
        } elseif ($offer->discount_type === 'percentage') {
            $offer->discount_value_usd = null;
        }

        if (! $offer->is_active) {
            return;
        }

        if (Offer::overlapsExistingActive($offer->product_id, $offer->starts_at, $offer->ends_at, $offer->id)) {
            throw new OverlappingOfferException($offer->product_id);
        }
    }

    public function saved(Offer $offer): void
    {
        CatalogCache::forgetHome();
    }

    public function deleted(Offer $offer): void
    {
        CatalogCache::forgetHome();
    }
}
