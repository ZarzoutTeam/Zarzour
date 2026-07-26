<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OutOfStockAttemptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly int $requestedQuantity,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('محاولة شراء منتج غير متوفر')
            ->body("طُلبت كمية {$this->requestedQuantity} من \"{$this->productName}\" وهي غير متوفرة بالمخزون")
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('danger')
            ->getDatabaseMessage();
    }
}
