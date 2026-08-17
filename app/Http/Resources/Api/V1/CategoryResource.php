<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->getFirstMedia('image');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'image' => $image ? [
                'medium' => $this->mediaUrl($image, 'medium'),
                'thumbnail' => $this->mediaUrl($image, 'thumbnail'),
            ] : null,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }

    private function mediaUrl(Media $media, string $conversion): string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getFullUrl($conversion)
            : $media->getFullUrl();
    }
}
