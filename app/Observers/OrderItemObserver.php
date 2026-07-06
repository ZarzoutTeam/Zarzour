<?php

namespace App\Observers;

use App\Events\OutOfStockAttempted;
use App\Exceptions\OutOfStockException;
use App\Models\OrderItem;
use App\Models\StockMovement;

class OrderItemObserver
{
    public function creating(OrderItem $item): void
    {
        $product = $item->product;

        if ($product->available_quantity < $item->quantity) {
            $order = $item->order;

            event(new OutOfStockAttempted($product->id, $item->quantity, $order->phone_number));

            throw new OutOfStockException($product->id, $item->quantity);
        }
    }

    public function created(OrderItem $item): void
    {
        $item->product->increment('reserved_quantity', $item->quantity);

        StockMovement::create([
            'product_id' => $item->product_id,
            'type' => 'reservation',
            'quantity' => $item->quantity,
            'order_id' => $item->order_id,
            'notes' => null,
        ]);
    }
}
