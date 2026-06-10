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
        'daily_limit' => env('OCR_DAILY_LIMIT', 3),
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

    /*
    |--------------------------------------------------------------------------
    | hCaptcha
    |--------------------------------------------------------------------------
    |
    | HCAPTCHA_SITE_KEY   — public site key rendered by the widget
    | HCAPTCHA_SECRET_KEY — private secret used server-side for verification
    |
    | When either key is empty the captcha rule passes through unconditionally,
    | which keeps local dev and self-hosted installs unaffected. Set both to
    | enable the captcha (recommended for any public SaaS deployment).
    |
    */

    'hcaptcha' => [
        'site_key' => env('HCAPTCHA_SITE_KEY'),
        'secret_key' => env('HCAPTCHA_SECRET_KEY'),
        'verify_url' => env('HCAPTCHA_VERIFY_URL', 'https://hcaptcha.com/siteverify'),
    ],

];
