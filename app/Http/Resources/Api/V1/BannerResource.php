<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Banner */
class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->getFirstMedia('image');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'priority' => $this->priority,
            'image' => [
                'hero' => $this->mediaUrl($image, 'hero') ?? '',
                'thumbnail' => $this->mediaUrl($image, 'thumbnail') ?? '',
            ],
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'slug' => $this->product->slug,
                'name' => $this->product->name,
                'price' => (float) $this->product->price_syp,
                'prices' => [
                    'SYP' => (float) $this->product->price_syp,
                    'USD' => $this->product->price_usd !== null ? (float) $this->product->price_usd : null,
                ],
                'image' => $this->mediaUrl($this->product->primaryImage, 'thumbnail'),
            ]),
        ];
    }

    private function mediaUrl(?Media $media, string $conversion): ?string
    {
        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion)
            ? $media->getFullUrl($conversion)
            : $media->getFullUrl();
    }
}
