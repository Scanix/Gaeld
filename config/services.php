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
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR (Optical Character Recognition)
    |--------------------------------------------------------------------------
    |
    | Used by the Quick Receipt feature to extract text from receipt photos.
    | The 'tesseract' driver requires the tesseract-ocr package installed
    | on the server (apt install tesseract-ocr tesseract-ocr-deu tesseract-ocr-fra).
    |
    */

    'ocr' => [
        'driver' => env('OCR_DRIVER', 'tesseract'),
        'tesseract_binary' => env('TESSERACT_BINARY', 'tesseract'),
        'tesseract_lang' => env('TESSERACT_LANG', 'deu+fra+eng'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google (GTM / GA4)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'gtm_id' => env('GTM_ID'),
        'ga4_id' => env('GA4_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    |
    | SLACK_HORIZON_WEBHOOK — Incoming Webhook URL for Horizon alerts
    |   (long wait, failed jobs). Create one at https://api.slack.com/messaging/webhooks
    |
    */

    'slack' => [
        'horizon_webhook' => env('SLACK_HORIZON_WEBHOOK'),
    ],

];
