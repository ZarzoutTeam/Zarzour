<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductListResource;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    use ApiResponse;

    private const MIN_FULLTEXT_LENGTH = 3;

    private const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $currency = strtoupper((string) $request->query('currency', 'SYP'));

        if (! in_array($currency, ['SYP', 'USD'], true)) {
            throw ValidationException::withMessages([
                'currency' => ['العملة يجب أن تكون SYP أو USD.'],
            ]);
        }

        $priceColumn = $currency === 'USD' ? 'price_usd' : 'price_syp';

        $products = QueryBuilder::for(Product::class)
            ->allowedFilters([
                AllowedFilter::callback('category_id', function (Builder $query, $value) {
                    $categoryIds = array_merge(
                        [(int) $value],
                        Category::query()->where('parent_id', $value)->pluck('id')->all(),
                    );

                    $query->whereIn('category_id', $categoryIds);
                }),
                AllowedFilter::callback('price_min', fn (Builder $query, $value) => $query->where($priceColumn, '>=', $value)),
                AllowedFilter::callback('price_max', fn (Builder $query, $value) => $query->where($priceColumn, '<=', $value)),
                AllowedFilter::callback('in_stock', function (Builder $query, $value) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereColumn('stock_quantity', '>', 'reserved_quantity');
                    } else {
                        $query->whereColumn('stock_quantity', '<=', 'reserved_quantity');
                    }
                }),
                AllowedFilter::callback('q', fn (Builder $query, $value) => $this->applySearch($query, (string) $value)),
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('price', $priceColumn),
            ])
            ->defaultSort('-created_at')
            ->where('is_active', true)
            ->with(['category', 'primaryImage'])
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'currency' => $currency,
            'items' => ProductListResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'category',
                'media',
                'discounts' => fn ($query) => $query->where('is_active', true)
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now())),
                'offers' => fn ($query) => $query->activeNow()->with('gifts.giftProduct'),
            ])
            ->firstOrFail();

        return $this->success(new ProductDetailResource($product));
    }

    private function applySearch(Builder $query, string $value): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        if (mb_strlen($value) < self::MIN_FULLTEXT_LENGTH || DB::connection()->getDriverName() !== 'mysql') {
            $query->where(function (Builder $inner) use ($value) {
                $inner->where('name', 'like', "%{$value}%")
                    ->orWhere('description', 'like', "%{$value}%");
            });

            return;
        }

        $query->whereFullText(['name', 'description'], $value);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), self::MAX_PER_PAGE);
    }
}
