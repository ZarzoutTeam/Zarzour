<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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
        'category_id',
        'is_active',
        'extra_info',
        'stock_quantity',
        'reserved_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'extra_info' => 'array',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(config('catalog.media.allowed_image_mimes'));

        $this->addMediaCollection('video')
            ->singleFile()
            ->acceptsMimeTypes(config('catalog.media.allowed_video_mimes'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->performOnCollections('images')
            ->nonQueued()
            ->width(300)
            ->format('webp');

        $this->addMediaConversion('medium')
            ->performOnCollections('images')
            ->nonQueued()
            ->width(800)
            ->format('webp');
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
