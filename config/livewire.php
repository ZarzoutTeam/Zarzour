<?php

$temporaryUploadMaxSize = max(
    (int) env('CATALOG_MAX_IMAGE_SIZE_KB', 20 * 1024),
    (int) env('CATALOG_MAX_VIDEO_SIZE_KB', 50 * 1024),
);

return [

    /*
     * Livewire receives every Filament upload before field-level validation.
     * This global ceiling must therefore allow the largest supported media
     * file; each image/video field still enforces its stricter own limit and
     * MIME allowlist.
     */
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:'.$temporaryUploadMaxSize],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],

];
