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
            'amounts' => [
                'unit_price' => $this->dualAmount($this->unit_price_snapshot, $this->unit_price_snapshot_usd),
                'line_total' => $this->dualAmount($this->line_total, $this->line_total_usd),
                'direct_discount_amount' => $this->dualAmount($this->direct_discount_amount, $this->direct_discount_amount_usd),
                'coupon_discount_amount' => $this->dualAmount($this->coupon_discount_amount, $this->coupon_discount_amount_usd),
                'offer_discount_amount' => $this->dualAmount($this->offer_discount_amount, $this->offer_discount_amount_usd),
            ],
        ];
    }

    /**
     * @return array{SYP: float|null, USD: float|null}
     */
    private function dualAmount(mixed $syp, mixed $usd): array
    {
        return [
            'SYP' => $syp !== null ? (float) $syp : null,
            'USD' => $usd !== null ? (float) $usd : null,
        ];
    }
}
