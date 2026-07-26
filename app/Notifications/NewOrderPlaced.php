<?php

namespace App\Notifications;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewOrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $customerName,
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
            ->title('طلب جديد')
            ->body("طلب رقم #{$this->orderId} من {$this->customerName}")
            ->icon(Heroicon::OutlinedRectangleStack)
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label('عرض الطلب')
                    ->url(OrderResource::getUrl('view', ['record' => $this->orderId]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
