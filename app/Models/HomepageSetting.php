<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HomepageSetting extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'key',
        'hero_media_type',
        'hero_enabled',
        'payment_methods',
    ];

    protected function casts(): array
    {
        return [
            'hero_enabled' => 'boolean',
            'payment_methods' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero_image')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(config('catalog.media.allowed_image_mimes'));

        $this->addMediaCollection('hero_video')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(config('catalog.media.allowed_video_mimes'));

        $this->addMediaCollection('hero_poster')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(config('catalog.media.allowed_image_mimes'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->performOnCollections('hero_image', 'hero_poster')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.thumbnail.dimension'),
                (int) config('catalog.media.conversions.thumbnail.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.thumbnail.quality'))
            ->format('webp')
            ->queued();

        $this->addMediaConversion('hero')
            ->performOnCollections('hero_image', 'hero_poster')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.hero.dimension'),
                (int) config('catalog.media.conversions.hero.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.hero.quality'))
            ->format('webp')
            ->queued();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function configuredPaymentMethods(): array
    {
        return config('checkout.payment_methods', []);
    }

    /**
     * @return list<string>
     */
    public static function enabledPaymentMethodKeys(): array
    {
        $configured = array_keys(static::configuredPaymentMethods());
        $selected = static::query()->value('payment_methods');

        if (is_string($selected)) {
            $selected = json_decode($selected, true);
        }

        if (! is_array($selected)) {
            return $configured;
        }

        return array_values(array_intersect($configured, $selected));
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function publicPaymentMethods(): array
    {
        $configured = static::configuredPaymentMethods();

        return collect(static::enabledPaymentMethodKeys())
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => $configured[$key]['label'],
                'description' => $configured[$key]['description'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public function paymentMethodsPayload(): array
    {
        $configured = static::configuredPaymentMethods();
        $selected = $this->payment_methods ?? array_keys($configured);

        return collect(array_intersect(array_keys($configured), $selected))
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => $configured[$key]['label'],
                'description' => $configured[$key]['description'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, url: string, poster_url: string|null, updated_at: string|null}|null
     */
    public function heroMediaPayload(): ?array
    {
        if (! $this->hero_enabled) {
            return null;
        }

        $collection = $this->hero_media_type === 'video' ? 'hero_video' : 'hero_image';
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        $poster = $this->hero_media_type === 'video'
            ? $this->getFirstMedia('hero_poster')
            : null;

        return [
            'type' => $this->hero_media_type,
            'url' => $this->hero_media_type === 'image'
                ? ($media->hasGeneratedConversion('hero') ? $media->getFullUrl('hero') : $media->getFullUrl())
                : $media->getFullUrl(),
            'poster_url' => $poster
                ? ($poster->hasGeneratedConversion('hero') ? $poster->getFullUrl('hero') : $poster->getFullUrl())
                : null,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    public function clearInactiveHeroMedia(): void
    {
        if ($this->hero_media_type === 'video') {
            $this->clearMediaCollection('hero_image');

            return;
        }

        $this->clearMediaCollection('hero_video');
        $this->clearMediaCollection('hero_poster');
    }
}
