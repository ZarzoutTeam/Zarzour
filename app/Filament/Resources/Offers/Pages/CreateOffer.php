<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Exceptions\OverlappingOfferException;
use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $giftProductId = Arr::pull($data, 'gift_product_id');

        try {
            $offer = Offer::create($data);
        } catch (OverlappingOfferException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            throw new Halt;
        }

        if ($offer->type === 'discount_with_gift' && $giftProductId) {
            $offer->gifts()->create(['gift_product_id' => $giftProductId]);
        }

        return $offer;
    }
}
