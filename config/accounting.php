<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Accounting Configuration
    |--------------------------------------------------------------------------
    */

    'default_currency' => env('ACCOUNTING_CURRENCY', 'CHF'),

    'fiscal_year_start' => env('FISCAL_YEAR_START', '01-01'),

    'decimal_places' => 2,

    'supported_currencies' => [
        'CHF', 'EUR', 'USD', 'GBP',
    ],

    'supported_locales' => ['en', 'fr', 'de', 'it', 'rm'],

    'pagination' => [
        'default' => 20,
        'webhooks' => 25,
        'reconciliation' => 30,
    ],

];
