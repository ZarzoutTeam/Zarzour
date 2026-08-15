<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BannerResource;
use App\Models\Banner;
use App\Support\CatalogCache;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BannerController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $banners = Cache::remember(CatalogCache::ACTIVE_BANNERS, now()->addMinutes(5), function () {
            return Banner::query()
                ->eligible()
                ->orderBy('priority')
                ->with(['media', 'product.primaryImage'])
                ->get();
        });

        return $this->success(BannerResource::collection($banners));
    }
}
