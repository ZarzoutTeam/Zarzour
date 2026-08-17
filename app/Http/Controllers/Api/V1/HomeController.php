<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BannerResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Discount;
use App\Models\HomepageSetting;
use App\Models\Offer;
use App\Models\Product;
use App\Support\CatalogCache;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    use ApiResponse;

    private const PRODUCTS_PER_SECTION = 10;

    public function index(): JsonResponse
    {
        $snapshot = Cache::remember(CatalogCache::HOME_SNAPSHOT, now()->addMinutes(5), fn () => [
            'banners' => $this->banners(),
            'categories' => $this->categories(),
            'latest_products' => $this->latestProducts(),
            'featured_products' => $this->featuredProducts(),
            'offered_products' => $this->offeredProducts(),
            'homepage_setting' => $this->homepageSetting(),
        ]);

        /** @var HomepageSetting|null $homepageSetting */
        $homepageSetting = $snapshot['homepage_setting'];

        return $this->success([
            'banners' => BannerResource::collection($snapshot['banners']),
            'categories' => CategoryResource::collection($snapshot['categories']),
            'latest_products' => ProductListResource::collection($snapshot['latest_products']),
            'featured_products' => ProductListResource::collection($snapshot['featured_products']),
            'offered_products' => ProductListResource::collection($snapshot['offered_products']),
            'hero_media' => $homepageSetting?->heroMediaPayload(),
            'payment_methods' => $homepageSetting?->paymentMethodsPayload()
                ?? HomepageSetting::publicPaymentMethods(),
        ]);
    }

    private function homepageSetting(): ?HomepageSetting
    {
        return HomepageSetting::query()
            ->with('media')
            ->first();
    }

    /**
     * @return Collection<int, Banner>
     */
    private function banners(): Collection
    {
        return Banner::query()
            ->eligible()
            ->orderBy('priority')
            ->with(['media', 'product.primaryImage'])
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'media',
                'children' => fn ($query) => $query->active()->orderBy('sort_order')->with('media'),
            ])
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function latestProducts(): Collection
    {
        return Product::query()
            ->active()
            ->with(['category', 'primaryImage'])
            ->latest()
            ->limit(self::PRODUCTS_PER_SECTION)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function featuredProducts(): Collection
    {
        return Product::query()
            ->active()
            ->where('is_featured', true)
            ->with(['category', 'primaryImage'])
            ->latest()
            ->limit(self::PRODUCTS_PER_SECTION)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function offeredProducts(): Collection
    {
        $discountedProductIds = Discount::query()->activeNow()->pluck('product_id');
        $offeredProductIds = Offer::query()->activeNow()->pluck('product_id');

        return Product::query()
            ->active()
            ->whereIn('id', $discountedProductIds->merge($offeredProductIds)->unique())
            ->with(['category', 'primaryImage'])
            ->latest()
            ->limit(self::PRODUCTS_PER_SECTION)
            ->get();
    }
}
