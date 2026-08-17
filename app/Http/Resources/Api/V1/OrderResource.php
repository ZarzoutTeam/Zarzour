<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'phone_number' => $this->phone_number,
            'province' => $this->whenLoaded('province', fn () => $this->province->name),
            'shipping_address' => $this->shipping_address,
            'extra_notes' => $this->extra_notes,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_discount_amount' => $this->coupon_discount_amount !== null
                ? (float) $this->coupon_discount_amount
                : null,
            'shipping_fee' => (float) $this->shipping_fee,
            'total' => (float) $this->total,
            'payment_method' => $this->payment_method,
            'currency' => $this->currency,
            'status' => $this->status,
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
        ];
    }
}
