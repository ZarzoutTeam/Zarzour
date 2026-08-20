<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
            'price' => (float) $this->price_syp,
            'prices' => [
                'SYP' => (float) $this->price_syp,
                'USD' => $this->price_usd !== null ? (float) $this->price_usd : null,
            ],
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
                // Keep `url` backward compatible for clients while serving the
                // optimized large image as soon as its queued conversion exists.
                'url' => $media->collection_name === 'images'
                    ? $this->mediaConversionUrl($media, 'large')
                    : $media->getFullUrl(),
                'original_url' => $media->collection_name === 'images' ? $media->getFullUrl() : null,
                'thumbnail_url' => $media->collection_name === 'images' ? $this->mediaConversionUrl($media, 'thumbnail') : null,
                'medium_url' => $media->collection_name === 'images' ? $this->mediaConversionUrl($media, 'medium') : null,
                'large_url' => $media->collection_name === 'images' ? $this->mediaConversionUrl($media, 'large') : null,
            ])),
            'discounts' => $this->whenLoaded('discounts', fn () => $this->discounts->map(fn ($discount) => [
                'id' => $discount->id,
                'type' => $discount->type,
                'value' => (float) $discount->value,
                'value_usd' => $discount->type === 'fixed' && $discount->value_usd !== null
                    ? (float) $discount->value_usd
                    : null,
                'values' => $discount->type === 'fixed' ? [
                    'SYP' => (float) $discount->value,
                    'USD' => $discount->value_usd !== null ? (float) $discount->value_usd : null,
                ] : null,
                'starts_at' => $discount->starts_at,
                'ends_at' => $discount->ends_at,
            ])),
            'offers' => $this->whenLoaded('offers', fn () => $this->offers->map(
                fn (Offer $offer): array => $this->offerPayload($offer)
            )),
        ];
    }

    private function mediaConversionUrl(Media $media, string $conversion): string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getFullUrl($conversion)
            : $media->getFullUrl();
    }

    /**
     * @return array<string, mixed>
     */
    private function offerPayload(Offer $offer): array
    {
        $giftProduct = $offer->gifts->first()?->giftProduct;

        return [
            'id' => $offer->id,
            'type' => $offer->type,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value !== null ? (float) $offer->discount_value : null,
            'discount_value_usd' => $offer->discount_type === 'fixed' && $offer->discount_value_usd !== null
                ? (float) $offer->discount_value_usd
                : null,
            'discount_values' => $offer->discount_type === 'fixed' ? [
                'SYP' => (float) $offer->discount_value,
                'USD' => $offer->discount_value_usd !== null ? (float) $offer->discount_value_usd : null,
            ] : null,
            'gift' => $giftProduct ? [
                'product_id' => $giftProduct->id,
                'name' => $giftProduct->name,
                'available' => $giftProduct->is_active && $giftProduct->available_quantity > 0,
            ] : null,
        ];
    }
}
