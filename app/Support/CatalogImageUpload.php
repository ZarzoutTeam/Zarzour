<?php

namespace App\Support;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class CatalogImageUpload
{
    public static function limitsDescription(): string
    {
        $maxSizeMegabytes = round((int) config('catalog.media.max_image_size_kb') / 1024, 1);
        $targetDimension = (int) config('catalog.media.upload_target_dimension_px');

        return "الحد الأقصى {$maxSizeMegabytes} ميغابايت؛ تُصغّر الصور الكبيرة تلقائياً إلى {$targetDimension} بكسل قبل الإرسال.";
    }

    /**
     * Apply the same defensive image pipeline to every catalog image input.
     * The browser downsizes trusted admin uploads before transmission, while the
     * dimensions rule protects the server if a client bypasses FilePond.
     */
    public static function configure(SpatieMediaLibraryFileUpload $upload): SpatieMediaLibraryFileUpload
    {
        $targetDimension = max(1, (int) config('catalog.media.upload_target_dimension_px'));
        $serverMaxDimension = max($targetDimension, (int) config('catalog.media.server_max_dimension_px'));

        return $upload
            ->image()
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth((string) $targetDimension)
            ->imageResizeTargetHeight((string) $targetDimension)
            ->imageResizeUpscale(false)
            ->maxSize((int) config('catalog.media.max_image_size_kb'))
            ->acceptedFileTypes(config('catalog.media.allowed_image_mimes'))
            ->rules([
                "dimensions:max_width={$serverMaxDimension},max_height={$serverMaxDimension}",
            ]);
    }

    /**
     * @param  list<string>  $orderedUuids
     */
    public static function reorderMedia(Model $record, string $collection, array $orderedUuids): void
    {
        if (! $record->exists) {
            return;
        }

        $orderedUuids = collect($orderedUuids)
            ->filter(static fn (mixed $uuid): bool => is_string($uuid) && filled($uuid))
            ->unique(strict: true)
            ->values()
            ->all();

        if ($orderedUuids === []) {
            return;
        }

        $mediaClass = method_exists($record, 'getMediaModel')
            ? $record->getMediaModel()
            : config('media-library.media_model', Media::class);
        $mediaModel = app($mediaClass);

        $mediaIdsByUuid = $mediaClass::query()
            ->where('model_type', $record->getMorphClass())
            ->where('model_id', $record->getKey())
            ->where('collection_name', $collection)
            ->whereIn('uuid', $orderedUuids)
            ->pluck($mediaModel->getKeyName(), 'uuid')
            ->all();

        $orderedMediaIds = collect($orderedUuids)
            ->map(static fn (string $uuid): mixed => $mediaIdsByUuid[$uuid] ?? null)
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->values()
            ->all();

        $mediaClass::setNewOrder($orderedMediaIds);

        // Force subsequent consumers to see the persisted order.
        $record->unsetRelation('media');
    }
}
