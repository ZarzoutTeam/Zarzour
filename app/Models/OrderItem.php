<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name_snapshot',
        'unit_price_snapshot',
        'unit_price_snapshot_usd',
        'quantity',
        'line_total',
        'line_total_usd',
        'direct_discount_amount',
        'direct_discount_amount_usd',
        'coupon_discount_amount',
        'coupon_discount_amount_usd',
        'offer_discount_amount',
        'offer_discount_amount_usd',
        'is_gift',
        'offer_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'decimal:2',
            'unit_price_snapshot_usd' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
            'line_total_usd' => 'decimal:2',
            'direct_discount_amount' => 'decimal:2',
            'direct_discount_amount_usd' => 'decimal:2',
            'coupon_discount_amount' => 'decimal:2',
            'coupon_discount_amount_usd' => 'decimal:2',
            'offer_discount_amount' => 'decimal:2',
            'offer_discount_amount_usd' => 'decimal:2',
            'is_gift' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
