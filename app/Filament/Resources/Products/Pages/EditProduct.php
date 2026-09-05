<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Concerns\RedirectsToPreviousListPage;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\CatalogImageUpload;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use RedirectsToPreviousListPage;

    protected static string $resource = ProductResource::class;

    /** @var list<string|null> */
    private array $submittedImageOrder = [];

    /** @var list<string> */
    private array $imageUuidsBeforeSave = [];

    protected function beforeSave(): void
    {
        $rawImages = $this->form->getRawState()['images'] ?? null;

        if (! is_array($rawImages)) {
            return;
        }

        // Existing media are UUID strings. A new or edited image is still a
        // temporary upload here, so null reserves its exact FilePond position.
        $this->submittedImageOrder = collect($rawImages)
            ->map(static fn (mixed $value): ?string => is_string($value) && filled($value) ? $value : null)
            ->values()
            ->all();

        $this->record->unsetRelation('media');
        $this->imageUuidsBeforeSave = $this->record
            ->getMedia('images')
            ->pluck('uuid')
            ->all();
    }

    protected function afterSave(): void
    {
        if ($this->submittedImageOrder === []) {
            return;
        }

        $this->record->unsetRelation('media');
        $currentMedia = $this->record->getMedia('images');
        $currentUuids = $currentMedia->pluck('uuid')->all();
        $currentUuidLookup = array_fill_keys($currentUuids, true);
        $newUuids = $currentMedia
            ->sortBy('id')
            ->pluck('uuid')
            ->reject(fn (string $uuid): bool => in_array($uuid, $this->imageUuidsBeforeSave, true))
            ->values();
        $orderedUuids = [];

        foreach ($this->submittedImageOrder as $submittedUuid) {
            $uuid = $submittedUuid;

            if ($uuid === null) {
                $uuid = $newUuids->shift();
            }

            if (! is_string($uuid) || ! isset($currentUuidLookup[$uuid])) {
                continue;
            }

            $orderedUuids[] = $uuid;
        }

        // Preserve any unexpected media (for example, a concurrent addition)
        // instead of dropping it from the sortable sequence.
        foreach ($currentUuids as $uuid) {
            if (! in_array($uuid, $orderedUuids, true)) {
                $orderedUuids[] = $uuid;
            }
        }

        CatalogImageUpload::reorderMedia($this->record, 'images', $orderedUuids);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Product $record, DeleteAction $action): void {
                    if (! $record->hasDeletionBlockingRelations()) {
                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('لا يمكن حذف المنتج')
                        ->body('هذا المنتج مرتبط بطلبات أو حركات مخزون أو خصومات أو عروض. ألغِ تفعيله بدلًا من حذفه، أو أزل الارتباطات غير التاريخية أولًا.')
                        ->send();

                    $action->halt();
                }),
        ];
    }
}
