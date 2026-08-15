<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class CatalogCache
{
    public const ACTIVE_BANNERS = 'banners.public.active';

    public const ACTIVE_CATEGORIES = 'categories.public.tree';

    public const ACTIVE_PROVINCES = 'provinces.public.active';

    public const HOME_SNAPSHOT = 'home.public.snapshot';

    public static function forgetBanners(): void
    {
        self::forget(self::ACTIVE_BANNERS);
    }

    public static function forgetCategories(): void
    {
        self::forget(self::ACTIVE_CATEGORIES);
    }

    public static function forgetHome(): void
    {
        self::forget(self::HOME_SNAPSHOT);
    }

    public static function forgetProvinces(): void
    {
        self::forget(self::ACTIVE_PROVINCES);
    }

    private static function forget(string $key): void
    {
        Cache::forget($key);

        if (DB::connection()->transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::forget($key));
        }
    }
}
