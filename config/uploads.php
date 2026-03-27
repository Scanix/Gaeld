<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Maximum file sizes in kilobytes for various upload types.
    | These values are used in validation rules across controllers.
    |
    */

    'max_size' => [
        'image' => env('UPLOAD_MAX_IMAGE_KB', 2048),   // 2 MB
        'receipt' => env('UPLOAD_MAX_RECEIPT_KB', 5120),  // 5 MB
        'document' => env('UPLOAD_MAX_DOCUMENT_KB', 10240), // 10 MB
        'import' => env('UPLOAD_MAX_IMPORT_KB', 2048),   // 2 MB
    ],

    'allowed_mimes' => [
        'image' => 'png,jpg,jpeg',
        'receipt' => 'pdf,jpg,jpeg,png',
        'document' => 'pdf,jpg,jpeg,png',
        'import' => 'csv,txt,json',
    ],

];
