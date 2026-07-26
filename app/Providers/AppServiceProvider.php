<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Province;
use App\Observers\BannerObserver;
use App\Observers\CategoryObserver;
use App\Observers\DiscountObserver;
use App\Observers\OfferObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductMediaObserver;
use App\Observers\ProductObserver;
use App\Observers\ProvinceObserver;
use Illuminate\Auth\Middleware\Authenticate;
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
        // This app has no named 'login' web route (API + Filament panel only, and
        // Filament uses its own auth middleware). Without this, an unauthenticated
        // request to any `auth:sanctum` route crashes with a raw 500
        // (RouteNotFoundException) instead of the clean 401 ApiExceptionRenderer
        // already produces for AuthenticationException.
        Authenticate::redirectUsing(fn () => null);

        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        Category::observe(CategoryObserver::class);
        Banner::observe(BannerObserver::class);
        Product::observe(ProductObserver::class);
        Media::observe(ProductMediaObserver::class);
        Offer::observe(OfferObserver::class);
        Discount::observe(DiscountObserver::class);
        Province::observe(ProvinceObserver::class);
    }
}
