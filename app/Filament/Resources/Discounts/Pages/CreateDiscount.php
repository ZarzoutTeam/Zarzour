<?php

namespace App\Filament\Resources\Discounts\Pages;

use App\Exceptions\OverlappingDiscountException;
use App\Filament\Resources\Discounts\DiscountResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (OverlappingDiscountException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            throw new Halt;
        }
    }
}
