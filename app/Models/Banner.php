<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Banner extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'subtitle',
        'product_id',
        'priority',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(config('catalog.media.allowed_image_mimes'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->performOnCollections('image')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.thumbnail.dimension'),
                (int) config('catalog.media.conversions.thumbnail.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.thumbnail.quality'))
            ->format('webp')
            ->queued();

        $this->addMediaConversion('medium')
            ->performOnCollections('image')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.medium.dimension'),
                (int) config('catalog.media.conversions.medium.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.medium.quality'))
            ->format('webp')
            ->queued();

        $this->addMediaConversion('hero')
            ->performOnCollections('image')
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<Banner>  $query
     * @return Builder<Banner>
     */
    public function scopeEligible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('media', fn (Builder $q) => $q->where('collection_name', 'image'))
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
