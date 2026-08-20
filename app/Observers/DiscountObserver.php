<?php

namespace App\Observers;

use App\Exceptions\OverlappingDiscountException;
use App\Models\Discount;
use App\Services\ExchangeRateService;
use App\Support\CatalogCache;

class DiscountObserver
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService) {}

    public function saving(Discount $discount): void
    {
        if ($discount->type === 'fixed' && $discount->value_usd !== null) {
            $converted = $this->exchangeRateService->convertUsdToSyp($discount->value_usd);

            if ($converted !== null) {
                $discount->value = $converted;
            }
        } elseif ($discount->type === 'percentage') {
            $discount->value_usd = null;
        }

        if (! $discount->is_active) {
            return;
        }

        if (Discount::overlapsExistingActive(
            $discount->product_id,
            $discount->starts_at,
            $discount->ends_at,
            $discount->id,
        )) {
            throw new OverlappingDiscountException($discount->product_id);
        }
    }

    public function saved(Discount $discount): void
    {
        CatalogCache::forgetHome();
    }

    public function deleted(Discount $discount): void
    {
        CatalogCache::forgetHome();
    }
}
