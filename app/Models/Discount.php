<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'type',
        'value',
        'value_usd',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'value_usd' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<Discount>  $query
     * @return Builder<Discount>
     */
    public function scopeActiveNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * A product must not have two active direct discounts whose date ranges
     * overlap, otherwise the selected discount would be ambiguous.
     */
    public static function overlapsExistingActive(
        int $productId,
        \DateTimeInterface|string|null $startsAt,
        \DateTimeInterface|string|null $endsAt,
        ?int $ignoreId = null,
    ): bool {
        return static::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $endsAt ?? '9999-12-31'))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startsAt ?? '0001-01-01'))
            ->exists();
    }
}
