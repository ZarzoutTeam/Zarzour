<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * Statuses counted as actual revenue — mirrors OrderObserver::FINAL_CONFIRMED_STATUSES.
     * pending/cancelled/expired orders never became real money and are excluded.
     */
    public const CONFIRMED_STATUSES = ['confirmed', 'shipped', 'delivered'];

    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * customers has no FK from orders; the two are linked by phone_number,
     * matching the same key OrderObserver::created() uses to upsert this row.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'phone_number', 'phone_number');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function confirmedOrders(): HasMany
    {
        return $this->orders()->whereIn('status', self::CONFIRMED_STATUSES);
    }
}
