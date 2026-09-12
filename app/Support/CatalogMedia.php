<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class CatalogMedia
{
    /**
     * @return array{id: int, url: string, original_url: string, thumbnail_url: string, medium_url: string, large_url: string}|null
     */
    public static function imagePayload(?Media $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'id' => $media->id,
            'url' => self::conversionUrl($media, 'large'),
            'original_url' => $media->getFullUrl(),
            'thumbnail_url' => self::conversionUrl($media, 'thumbnail'),
            'medium_url' => self::conversionUrl($media, 'medium'),
            'large_url' => self::conversionUrl($media, 'large'),
        ];
    }

    public static function conversionUrl(Media $media, string $conversion): string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getFullUrl($conversion)
            : $media->getFullUrl();
    }
}
