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

    // CE features (enabled by default)
    'bank_import' => env('FEATURE_BANK_IMPORT', true),

    // EE features (disabled by default)
    'auto_reconciliation' => env('FEATURE_AUTO_RECONCILIATION', false),
    'bank_sync' => env('FEATURE_BANK_SYNC', false),
    'saas' => env('FEATURE_SAAS', false),
    'automation' => env('FEATURE_AUTOMATION', false),
    'multi_currency' => env('FEATURE_MULTI_CURRENCY', false),
    'api_access' => env('FEATURE_API_ACCESS', false),
    'rule_engine' => env('FEATURE_RULE_ENGINE', false),
    'advanced_permissions' => env('FEATURE_ADVANCED_PERMISSIONS', false),

];
