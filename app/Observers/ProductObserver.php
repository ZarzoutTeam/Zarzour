<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ExchangeRateService;
use App\Support\CatalogCache;

class ProductObserver
{
    private const BANNER_RELEVANT_FIELDS = ['name', 'slug', 'price_syp', 'price_usd'];

    private const HOME_RELEVANT_FIELDS = ['name', 'slug', 'price_syp', 'price_usd', 'is_active', 'is_featured', 'category_id'];

    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function saving(Product $product): void
    {
        if ($product->price_usd === null) {
            return;
        }

        $convertedPrice = $this->exchangeRateService->convertUsdToSyp($product->price_usd);

        if ($convertedPrice !== null) {
            $product->price_syp = $convertedPrice;
        }
    }

    public function saved(Product $product): void
    {
        if ($product->wasChanged(self::BANNER_RELEVANT_FIELDS) && $product->banners()->exists()) {
            CatalogCache::forgetBanners();
        }

        if ($product->wasRecentlyCreated || $product->wasChanged(self::HOME_RELEVANT_FIELDS)) {
            CatalogCache::forgetHome();
        }
    }

    public function deleted(Product $product): void
    {
        CatalogCache::forgetBanners();
        CatalogCache::forgetHome();
    }
}
