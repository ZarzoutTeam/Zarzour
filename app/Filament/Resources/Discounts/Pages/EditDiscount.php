<?php

namespace App\Filament\Resources\Discounts\Pages;

use App\Exceptions\OverlappingDiscountException;
use App\Filament\Resources\Discounts\DiscountResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditDiscount extends EditRecord
{
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (OverlappingDiscountException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            throw new Halt;
        }
    }
}
