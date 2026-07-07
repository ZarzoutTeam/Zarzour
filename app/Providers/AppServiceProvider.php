<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Observers\CategoryObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use Illuminate\Support\ServiceProvider;

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
    }
}
