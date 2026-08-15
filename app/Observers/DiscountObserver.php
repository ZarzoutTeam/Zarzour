<?php

namespace App\Observers;

use App\Models\Discount;
use App\Support\CatalogCache;

class DiscountObserver
{
    public function saved(Discount $discount): void
    {
        CatalogCache::forgetHome();
    }

    public function deleted(Discount $discount): void
    {
        CatalogCache::forgetHome();
    }
}
