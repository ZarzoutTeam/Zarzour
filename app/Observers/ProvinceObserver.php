<?php

namespace App\Observers;

use App\Models\Province;
use App\Support\CatalogCache;

class ProvinceObserver
{
    public function saved(Province $province): void
    {
        CatalogCache::forgetProvinces();
    }

    public function deleted(Province $province): void
    {
        CatalogCache::forgetProvinces();
    }
}
