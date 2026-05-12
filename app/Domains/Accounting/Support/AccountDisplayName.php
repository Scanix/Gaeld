<?php

namespace App\Domains\Accounting\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Resolves a localized display name for an account code.
 *
 * Seeded system accounts have translations under `lang/{locale}/accounts.php`;
 * user-created accounts fall back to the stored name.
 */
final class AccountDisplayName
{
    public static function for(string $code, ?string $fallback = null): string
    {
        $key = 'accounts.'.$code;

        if (Lang::has($key)) {
            return (string) __($key);
        }

        return (string) ($fallback ?? $code);
    }
}
