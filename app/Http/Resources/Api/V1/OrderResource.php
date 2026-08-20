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
            'exchange_rate' => $this->exchange_rate_snapshot !== null ? [
                'base_currency' => 'USD',
                'quote_currency' => 'SYP',
                'rate' => (float) $this->exchange_rate_snapshot,
            ] : null,
            'amounts' => [
                'subtotal' => $this->dualAmount($this->subtotal, $this->subtotal_usd),
                'discount_amount' => $this->dualAmount($this->discount_amount, $this->discount_amount_usd),
                'coupon_discount_amount' => $this->dualAmount($this->coupon_discount_amount, $this->coupon_discount_amount_usd),
                'shipping_fee' => $this->dualAmount($this->shipping_fee, $this->shipping_fee_usd),
                'total' => $this->dualAmount($this->total, $this->total_usd),
            ],
            'payment' => [
                'currency' => $this->currency,
                'subtotal' => $this->paymentAmount($this->subtotal, $this->subtotal_usd),
                'discount_amount' => $this->paymentAmount($this->discount_amount, $this->discount_amount_usd),
                'coupon_discount_amount' => $this->paymentAmount($this->coupon_discount_amount, $this->coupon_discount_amount_usd),
                'total_before_shipping' => $this->paymentAmount(
                    (float) $this->total - (float) $this->shipping_fee,
                    $this->total_usd !== null && $this->shipping_fee_usd !== null
                        ? (float) $this->total_usd - (float) $this->shipping_fee_usd
                        : null,
                ),
                'shipping_fee' => $this->paymentAmount($this->shipping_fee, $this->shipping_fee_usd),
                'total' => $this->paymentAmount($this->total, $this->total_usd),
            ],
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

    private function paymentAmount(mixed $syp, mixed $usd): ?float
    {
        $amount = $this->currency === 'USD' ? $usd : $syp;

        return $amount !== null ? (float) $amount : null;
    }
}
