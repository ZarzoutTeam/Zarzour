<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\CatalogImageUpload;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $rawImages = $this->form->getRawState()['images'] ?? null;

        if (! is_array($rawImages)) {
            return;
        }

        // Once the media relationship is saved, the values are the persisted
        // UUIDs while their array order is the final FilePond drag order.
        $orderedUuids = collect($rawImages)
            ->filter(static fn (mixed $uuid): bool => is_string($uuid) && filled($uuid))
            ->values()
            ->all();

        CatalogImageUpload::reorderMedia($this->record, 'images', $orderedUuids);
    }
}
