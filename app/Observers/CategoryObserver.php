<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        Cache::forget('categories.public.tree');
        Cache::forget('home.public.snapshot');
    }

    public function deleted(Category $category): void
    {
        Cache::forget('categories.public.tree');
        Cache::forget('home.public.snapshot');
    }
}
