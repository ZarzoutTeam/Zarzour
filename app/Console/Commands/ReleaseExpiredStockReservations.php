<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Console\Command;

class ReleaseExpiredStockReservations extends Command
{
    protected $signature = 'orders:release-expired-reservations';

    protected $description = 'يحرر حجز المخزون للطلبات المعلّقة التي تجاوزت مهلة الحجز ويغيّر حالتها إلى expired';

    public function handle(OrderObserver $observer): int
    {
        $orders = Order::query()
            ->where('status', 'pending')
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->get();

        foreach ($orders as $order) {
            $observer->releaseReservation($order, 'expired_release');
            $order->update(['status' => 'expired']);
        }

        $this->info("تم تحرير حجز {$orders->count()} طلب منتهي الصلاحية.");

        return self::SUCCESS;
    }
}
