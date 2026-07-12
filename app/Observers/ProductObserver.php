<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    private const BANNER_RELEVANT_FIELDS = ['name', 'slug', 'price'];

    private const HOME_RELEVANT_FIELDS = ['name', 'slug', 'price', 'is_active', 'is_featured', 'category_id'];

    public function saved(Product $product): void
    {
        if ($product->wasChanged(self::BANNER_RELEVANT_FIELDS) && $product->banners()->exists()) {
            Cache::forget('banners.public.active');
        }

        if ($product->wasRecentlyCreated || $product->wasChanged(self::HOME_RELEVANT_FIELDS)) {
            Cache::forget('home.public.snapshot');
        }
    }

    public function deleted(Product $product): void
    {
        Cache::forget('home.public.snapshot');
    }
}
