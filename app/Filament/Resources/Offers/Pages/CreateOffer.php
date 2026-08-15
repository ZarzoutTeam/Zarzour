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
use Illuminate\Support\Facades\DB;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $giftProductId = Arr::pull($data, 'gift_product_id');

        if (($data['type'] ?? null) === 'gift_only') {
            $data['discount_type'] = null;
            $data['discount_value'] = null;
        }

        try {
            return DB::transaction(function () use ($data, $giftProductId): Offer {
                $offer = Offer::create($data);

                if ($offer->hasGift() && $giftProductId) {
                    $offer->gifts()->create(['gift_product_id' => $giftProductId]);
                }

                return $offer;
            });
        } catch (OverlappingOfferException $exception) {
            Notification::make()
                ->danger()
                ->title($exception->getMessage())
                ->send();

            throw new Halt;
        }
    }
}
