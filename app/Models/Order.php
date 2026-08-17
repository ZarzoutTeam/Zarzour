<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * Status transitions permitted anywhere in the system (model/observer level).
     * The Filament admin UI only exposes a subset of these as selectable actions —
     * see OrderResource's status-change action for the UI-level restriction.
     *
     * @var array<string, list<string>>
     */
    public const VALID_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled', 'expired'],
        'confirmed' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    /**
     * `total` stores the grand (shipping-inclusive) total — equivalent to
     * PriceCalculationService::calculate()'s 'grand_total' key, not its
     * 'total_before_shipping' key.
     */
    protected $fillable = [
        'user_id',
        'customer_name',
        'phone_number',
        'province_id',
        'shipping_address',
        'extra_notes',
        'subtotal',
        'discount_amount',
        'coupon_discount_amount',
        'shipping_fee',
        'total',
        'coupon_id',
        'applied_offer_id',
        'payment_method',
        'currency',
        'status',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'coupon_discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'reserved_until' => 'datetime',
        ];
    }

    public static function isValidStatusTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function appliedOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'applied_offer_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
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
}
