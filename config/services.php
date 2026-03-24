<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    |
    | STRIPE_KEY       — publishable key (used in the frontend / Inertia pages)
    | STRIPE_SECRET    — secret key (server-side API calls)
    | STRIPE_WEBHOOK_SECRET — whsec_... from `stripe listen` or the dashboard
    |
    */

    'stripe' => [
        'key'    => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

];
