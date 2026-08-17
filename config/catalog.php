<?php

return [

    /*
     * Upload limits for catalog and homepage media. Sizes are in kilobytes to match
     * Filament's FileUpload::maxSize() expectations.
     */
    'media' => [
        // Source files may be large, but FilePond resizes them in the browser before
        // upload and the server rejects anything that bypasses that safety boundary.
        'max_image_size_kb' => (int) env('CATALOG_MAX_IMAGE_SIZE_KB', 20 * 1024),
        'max_video_size_kb' => (int) env('CATALOG_MAX_VIDEO_SIZE_KB', 50 * 1024),
        'max_product_images' => (int) env('CATALOG_MAX_PRODUCT_IMAGES', 10),
        'upload_target_dimension_px' => (int) env('CATALOG_IMAGE_UPLOAD_TARGET_PX', 2560),
        'server_max_dimension_px' => (int) env('CATALOG_IMAGE_SERVER_MAX_PX', 3000),

        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_video_mimes' => ['video/mp4'],

        'conversions' => [
            'thumbnail' => ['dimension' => 400, 'quality' => 75],
            'medium' => ['dimension' => 1200, 'quality' => 82],
            'large' => ['dimension' => 1920, 'quality' => 85],
            'hero' => ['dimension' => 2560, 'quality' => 85],
        ],
    ],

];
