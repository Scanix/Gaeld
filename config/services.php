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
    | Telegram
    |--------------------------------------------------------------------------
    |
    | TELEGRAM_BOT_TOKEN — Bot token from @BotFather
    | TELEGRAM_CHAT_ID   — Chat or group ID to receive Horizon alerts
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

];
