<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Banner */
class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'priority' => $this->priority,
            'image' => [
                'hero' => $this->getFirstMediaUrl('image', 'hero'),
                'thumbnail' => $this->getFirstMediaUrl('image', 'thumbnail'),
            ],
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'slug' => $this->product->slug,
                'name' => $this->product->name,
                'price' => (float) $this->product->price,
                'image' => $this->product->primaryImage?->getUrl('thumbnail'),
            ]),
        ];
    }
}
