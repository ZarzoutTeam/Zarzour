<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ChangeOrderStatusAction
{
    private const STATUS_LABELS = [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
        'cancelled' => 'ملغى',
        'expired' => 'منتهي الصلاحية',
    ];

    public static function make(): Action
    {
        return Action::make('changeStatus')
            ->label('تغيير الحالة')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (Order $record): bool => self::selectableOptions($record) !== [])
            ->schema(fn (Order $record) => [
                Select::make('status')
                    ->label('الحالة الجديدة')
                    ->options(self::selectableOptions($record))
                    ->required(),
            ])
            ->action(function (Order $record, array $data): void {
                try {
                    $record->update(['status' => $data['status']]);
                } catch (InvalidOrderStatusTransitionException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('تم تحديث حالة الطلب بنجاح')->success()->send();
            });
    }

    /**
     * Only offers transitions allowed by Order::VALID_TRANSITIONS, minus 'expired'
     * which is reserved for the scheduled reservation-release command only.
     *
     * @return array<string, string>
     */
    private static function selectableOptions(Order $record): array
    {
        $next = array_diff(Order::VALID_TRANSITIONS[$record->status], ['expired']);

        return collect($next)
            ->mapWithKeys(fn (string $status) => [$status => self::STATUS_LABELS[$status] ?? $status])
            ->all();
    }
}
