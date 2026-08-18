<?php

namespace App\Observers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Support\CatalogCache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductMediaObserver
{
    public function saved(Media $media): void
    {
        $this->invalidateIfRelevant($media);
    }

    public function deleted(Media $media): void
    {
        $this->invalidateIfRelevant($media);
    }

    private function invalidateIfRelevant(Media $media): void
    {
        if ($media->model_type === Category::class && $media->collection_name === 'image') {
            CatalogCache::forgetCategories();
            CatalogCache::forgetHome();

            return;
        }

        if (
            $media->model_type === Banner::class
            && in_array($media->collection_name, ['image', 'video'], true)
        ) {
            CatalogCache::forgetBanners();
            CatalogCache::forgetHome();

            return;
        }

        if ($media->model_type === HomepageSetting::class) {
            CatalogCache::forgetHome();

            return;
        }

        if ($media->model_type !== Product::class || $media->collection_name !== 'images') {
            return;
        }

        CatalogCache::forgetHome();

        if (Product::query()->whereKey($media->model_id)->whereHas('banners')->exists()) {
            CatalogCache::forgetBanners();
        }
    }
}
