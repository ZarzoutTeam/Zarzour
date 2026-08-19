<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'price_syp',
        'price_usd',
        'category_id',
        'is_active',
        'is_featured',
        'extra_info',
        'stock_quantity',
        'reserved_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price_syp' => 'decimal:2',
            'price_usd' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'extra_info' => 'array',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    /**
     * Backward-compatible alias for integrations and tests that still use `price`.
     * Checkout in API v1 remains denominated in SYP.
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn (): mixed => $this->price_syp,
            set: fn (mixed $value): array => ['price_syp' => $value],
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public')
            ->acceptsMimeTypes(config('catalog.media.allowed_image_mimes'));

        $this->addMediaCollection('video')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes(config('catalog.media.allowed_video_mimes'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->performOnCollections('images')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.thumbnail.dimension'),
                (int) config('catalog.media.conversions.thumbnail.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.thumbnail.quality'))
            ->format('webp')
            ->queued();

        $this->addMediaConversion('medium')
            ->performOnCollections('images')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.medium.dimension'),
                (int) config('catalog.media.conversions.medium.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.medium.quality'))
            ->format('webp')
            ->queued();

        $this->addMediaConversion('large')
            ->performOnCollections('images')
            ->fit(
                Fit::Max,
                (int) config('catalog.media.conversions.large.dimension'),
                (int) config('catalog.media.conversions.large.dimension'),
            )
            ->quality((int) config('catalog.media.conversions.large.quality'))
            ->format('webp')
            ->queued();
    }

    /**
     * أول صورة بمجموعة الصور فقط (حسب ترتيب الرفع) - لتفادي تحميل كل الميديا بقوائم الكتالوغ.
     *
     * @return MorphOne<Media, $this>
     */
    public function primaryImage(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')
            ->ofMany(['order_column' => 'min'], function (Builder $query) {
                $query->where('collection_name', 'images');
            });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Discount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return HasMany<OfferGift, $this>
     */
    public function offerGiftLinks(): HasMany
    {
        return $this->hasMany(OfferGift::class, 'gift_product_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<Banner, $this>
     */
    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function hasDeletionBlockingRelations(): bool
    {
        return $this->discounts()->exists()
            || $this->offers()->exists()
            || $this->offerGiftLinks()->exists()
            || $this->orderItems()->exists()
            || $this->stockMovements()->exists();
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->stock_quantity - $this->reserved_quantity;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
