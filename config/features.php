<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features are enabled in this installation.
    | Community Edition disables SaaS-only features by default.
    |
    */

    'bank_sync' => env('FEATURE_BANK_SYNC', false),
    'saas' => env('FEATURE_SAAS', false),
    'automation' => env('FEATURE_AUTOMATION', false),
    'multi_currency' => env('FEATURE_MULTI_CURRENCY', false),
    'api_access' => env('FEATURE_API_ACCESS', false),

];
