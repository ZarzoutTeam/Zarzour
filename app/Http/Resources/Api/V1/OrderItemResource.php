<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'name' => $this->product_name_snapshot,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price_snapshot,
            'line_total' => (float) $this->line_total,
            'direct_discount_amount' => $this->direct_discount_amount !== null ? (float) $this->direct_discount_amount : null,
            'coupon_discount_amount' => $this->coupon_discount_amount !== null ? (float) $this->coupon_discount_amount : null,
            'offer_discount_amount' => $this->offer_discount_amount !== null ? (float) $this->offer_discount_amount : null,
            'offer_id' => $this->offer_id,
            'is_gift' => $this->is_gift,
        ];
    }
}
