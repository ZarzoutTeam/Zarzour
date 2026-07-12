<?php

namespace App\Observers;

use App\Models\Province;
use Illuminate\Support\Facades\Cache;

class ProvinceObserver
{
    public function saved(Province $province): void
    {
        Cache::forget('provinces.public.active');
    }

    public function deleted(Province $province): void
    {
        Cache::forget('provinces.public.active');
    }
}
