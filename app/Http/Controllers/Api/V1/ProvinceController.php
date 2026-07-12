<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProvinceResource;
use App\Models\Province;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProvinceController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $provinces = Cache::remember('provinces.public.active', now()->addMinutes(5), function () {
            return Province::query()
                ->active()
                ->orderBy('name')
                ->get();
        });

        return $this->success(ProvinceResource::collection($provinces));
    }
}
