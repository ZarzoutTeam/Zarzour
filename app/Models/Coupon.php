<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'value_usd',
        'max_discount_amount',
        'max_discount_amount_usd',
        'scope',
        'phone_number',
        'min_order_amount',
        'min_order_amount_usd',
        'usage_limit',
        'per_customer_usage_limit',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'value_usd' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'max_discount_amount_usd' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'min_order_amount_usd' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_customer_usage_limit' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Coupon codes are canonicalized so admin input and API lookup remain
     * case-insensitive across MySQL and SQLite.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => strtoupper(trim((string) $value)),
        );
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
