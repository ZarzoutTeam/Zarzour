<?php

return [

    /*
     * Upload limits for product media. Sizes are in kilobytes to match
     * Filament's FileUpload::maxSize() expectations.
     */
    'media' => [
        'max_image_size_kb' => (int) env('CATALOG_MAX_IMAGE_SIZE_KB', 5 * 1024),
        'max_video_size_kb' => (int) env('CATALOG_MAX_VIDEO_SIZE_KB', 50 * 1024),

        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_video_mimes' => ['video/mp4'],
    ],

];
