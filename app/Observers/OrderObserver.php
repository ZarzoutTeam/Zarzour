<?php

namespace App\Observers;

use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StockMovement;

class OrderObserver
{
    private const FINAL_CONFIRMED_STATUSES = ['confirmed', 'shipped', 'delivered'];

    public function creating(Order $order): void
    {
        if (! $order->reserved_until && $order->status === 'pending') {
            $hours = (int) Setting::get('stock_reservation_timeout_hours', 24);
            $order->reserved_until = now()->addHours($hours);
        }
    }

    public function created(Order $order): void
    {
        $attributes = ['name' => $order->customer_name, 'address' => $order->shipping_address];

        if ($order->user_id !== null) {
            $attributes['user_id'] = $order->user_id;
        }

        Customer::updateOrCreate(
            ['phone_number' => $order->phone_number],
            $attributes,
        );
    }

    public function updating(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }

        $original = $order->getOriginal('status');
        $new = $order->status;

        if (! Order::isValidStatusTransition($original, $new)) {
            throw new InvalidOrderStatusTransitionException($original, $new);
        }

        if ($original === 'pending' && in_array($new, self::FINAL_CONFIRMED_STATUSES, true)) {
            $this->confirmReservation($order);
        } elseif ($original === 'pending' && $new === 'cancelled') {
            $this->releaseReservation($order, 'release');
        } elseif ($original === 'confirmed' && $new === 'cancelled') {
            $this->restockAfterConfirmedCancellation($order);
        }
    }

    private function confirmReservation(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            $product->decrement('stock_quantity', $item->quantity);
            $product->decrement('reserved_quantity', $item->quantity);

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'confirmed_deduction',
                'quantity' => $item->quantity,
                'order_id' => $order->id,
                'notes' => null,
            ]);
        }
    }

    public function releaseReservation(Order $order, string $type): void
    {
        foreach ($order->items as $item) {
            $item->product->decrement('reserved_quantity', $item->quantity);

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => $type,
                'quantity' => $item->quantity,
                'order_id' => $order->id,
                'notes' => null,
            ]);
        }
    }

    /**
     * A confirmed order already had stock permanently deducted (not just reserved),
     * so cancelling it must restock the products rather than releasing a reservation.
     */
    private function restockAfterConfirmedCancellation(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->product->increment('stock_quantity', $item->quantity);

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'cancelled_restock',
                'quantity' => $item->quantity,
                'order_id' => $order->id,
                'notes' => null,
            ]);
        }
    }
}
