<?php

namespace App\Listeners;

use App\Events\OutOfStockAttempted;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OutOfStockAttemptedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfOutOfStockAttempt
{
    /**
     * This event is always raised from inside the order-creation DB transaction
     * (OrderItemObserver::creating() throws right after dispatching it), and that
     * transaction is guaranteed to roll back as a result. A queued notification
     * dispatched here would have its `jobs` row rolled back along with everything
     * else, so the actual send is deferred to app termination — after the
     * transaction has definitely resolved either way.
     */
    public function handle(OutOfStockAttempted $event): void
    {
        $productName = Product::query()->whereKey($event->productId)->value('name')
            ?? "#{$event->productId}";

        app()->terminating(function () use ($event, $productName): void {
            $recipients = User::query()->permission('ViewAny:Order')->get();

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new OutOfStockAttemptedNotification(
                $event->productId,
                $productName,
                $event->requestedQuantity,
            ));
        });
    }
}
