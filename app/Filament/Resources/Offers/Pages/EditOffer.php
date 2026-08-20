<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Exceptions\OverlappingOfferException;
use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Offer $record */
        $record = $this->getRecord();
        $data['gift_product_id'] = $record->gifts->first()?->gift_product_id;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Offer $record */
        $giftProductId = Arr::pull($data, 'gift_product_id');

        if (($data['type'] ?? null) === 'gift_only') {
            $data['discount_type'] = null;
            $data['discount_value'] = null;
            $data['discount_value_usd'] = null;
        }

        try {
            return DB::transaction(function () use ($record, $data, $giftProductId): Offer {
                $record->update($data);

                if ($record->hasGift() && $giftProductId) {
                    $record->gifts()->updateOrCreate([], ['gift_product_id' => $giftProductId]);
                } else {
                    $record->gifts()->delete();
                }

                return $record;
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
