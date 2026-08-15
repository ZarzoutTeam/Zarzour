<?php

namespace App\Observers;

use App\Models\Banner;
use App\Support\CatalogCache;

class BannerObserver
{
    public function saved(Banner $banner): void
    {
        CatalogCache::forgetBanners();
        CatalogCache::forgetHome();
    }

    public function deleted(Banner $banner): void
    {
        CatalogCache::forgetBanners();
        CatalogCache::forgetHome();
    }
}
