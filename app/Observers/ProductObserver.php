<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    private const BANNER_RELEVANT_FIELDS = ['name', 'slug', 'price'];

    public function saved(Product $product): void
    {
        if ($product->wasChanged(self::BANNER_RELEVANT_FIELDS) && $product->banners()->exists()) {
            Cache::forget('banners.public.active');
        }
    }
}
