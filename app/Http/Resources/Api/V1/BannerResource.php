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
        $mediaType = $this->media_type === 'video' ? 'video' : 'image';
        $media = $this->getFirstMedia($mediaType);
        $image = $this->getFirstMedia('image');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'priority' => $this->priority,
            'media_type' => $mediaType,
            'media' => $media ? [
                'type' => $mediaType,
                'url' => $mediaType === 'image'
                    ? $this->mediaUrl($media, 'hero')
                    : $media->getFullUrl(),
                'thumbnail_url' => $mediaType === 'image'
                    ? $this->mediaUrl($media, 'thumbnail')
                    : null,
            ] : null,
            'image' => [
                'hero' => $this->mediaUrl($image, 'hero') ?? '',
                'thumbnail' => $this->mediaUrl($image, 'thumbnail') ?? '',
            ],
            'video_url' => $mediaType === 'video' ? $media?->getFullUrl() : null,
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
