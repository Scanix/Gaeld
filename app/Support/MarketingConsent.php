<?php

namespace App\Support;

use Illuminate\Http\Request;

class MarketingConsent
{
    public static function accepted(Request $request): bool
    {
        $cookie = $request->cookie('gaeld_cookie_consent');
        if (! is_string($cookie) || strlen($cookie) > 8192) {
            return false;
        }

        $consent = json_decode($cookie, true);

        return is_array($consent)
            && ($consent['revision'] ?? null) === (int) config('services.google.consent_revision', 1)
            && is_array($consent['categories'] ?? null)
            && in_array('marketing', $consent['categories'], true);
    }
}
