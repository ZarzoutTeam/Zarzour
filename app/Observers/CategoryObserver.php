<?php

namespace App\Observers;

use App\Models\Category;
use App\Support\CatalogCache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        CatalogCache::forgetCategories();
        CatalogCache::forgetHome();
    }

    public function deleted(Category $category): void
    {
        CatalogCache::forgetCategories();
        CatalogCache::forgetHome();
    }
}
