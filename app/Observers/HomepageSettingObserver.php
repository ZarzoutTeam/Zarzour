<?php

namespace App\Observers;

use App\Models\HomepageSetting;
use App\Support\CatalogCache;

class HomepageSettingObserver
{
    public function saved(HomepageSetting $homepageSetting): void
    {
        CatalogCache::forgetHome();
    }

    public function deleted(HomepageSetting $homepageSetting): void
    {
        CatalogCache::forgetHome();
    }
}
