<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Observers\BannerObserver;
use App\Observers\CategoryObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductMediaObserver;
use App\Observers\ProductObserver;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        Category::observe(CategoryObserver::class);
        Banner::observe(BannerObserver::class);
        Product::observe(ProductObserver::class);
        Media::observe(ProductMediaObserver::class);
    }
}
