<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Support\CatalogCache;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Cache::remember(CatalogCache::ACTIVE_CATEGORIES, now()->addHours(6), function () {
            return Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->with(['children' => fn ($query) => $query->active()->orderBy('sort_order')])
                ->get();
        });

        return $this->success(CategoryResource::collection($categories));
    }
}
