<?php

namespace App\Observers;

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
        Customer::updateOrCreate(
            ['phone_number' => $order->phone_number],
            ['name' => $order->customer_name, 'address' => $order->shipping_address],
        );
    }

    public function updating(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }

        $original = $order->getOriginal('status');
        $new = $order->status;

        if ($original === 'pending' && in_array($new, self::FINAL_CONFIRMED_STATUSES, true)) {
            $this->confirmReservation($order);
        } elseif ($original === 'pending' && $new === 'cancelled') {
            $this->releaseReservation($order, 'release');
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
}
