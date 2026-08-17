<?php

namespace App\Support;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

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
}
