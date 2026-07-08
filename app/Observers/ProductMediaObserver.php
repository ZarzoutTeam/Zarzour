<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
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
        if ($media->model_type !== Product::class || $media->collection_name !== 'images') {
            return;
        }

        if (Product::whereKey($media->model_id)->whereHas('banners')->exists()) {
            Cache::forget('banners.public.active');
        }
    }
}
