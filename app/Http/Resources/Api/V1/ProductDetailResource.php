<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'extra_info' => $this->extra_info,
            'stock_quantity' => $this->stock_quantity,
            'available_quantity' => $this->available_quantity,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($media) => [
                'id' => $media->id,
                'collection' => $media->collection_name,
                'url' => $media->getUrl(),
                'thumbnail_url' => $media->collection_name === 'images' ? $media->getUrl('thumbnail') : null,
                'medium_url' => $media->collection_name === 'images' ? $media->getUrl('medium') : null,
            ])),
            'discounts' => $this->whenLoaded('discounts', fn () => $this->discounts->map(fn ($discount) => [
                'id' => $discount->id,
                'type' => $discount->type,
                'value' => (float) $discount->value,
                'starts_at' => $discount->starts_at,
                'ends_at' => $discount->ends_at,
            ])),
            'offers' => $this->whenLoaded('offers', fn () => $this->offers->map(fn ($offer) => [
                'id' => $offer->id,
                'type' => $offer->type,
                'discount_type' => $offer->discount_type,
                'discount_value' => (float) $offer->discount_value,
            ])),
        ];
    }
}
