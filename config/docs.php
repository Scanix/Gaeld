<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentation Configuration
    |--------------------------------------------------------------------------
    */

    'base_url' => env('DOCS_BASE_URL', 'http://localhost:3000'),

    'routes' => [
        'invoices' => '/docs/invoices',
        'expenses' => '/docs/expenses',
        'accounting' => '/docs/accounting',
        'reports' => '/docs/reports',
        'vat' => '/docs/vat',
        'banking' => '/docs/banking',
        'getting-started' => '/docs/getting-started',
    ],

];
