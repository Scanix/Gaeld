<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validate an IBAN (International Bank Account Number) using ISO 13616 checksum.
 *
 * Supports all IBAN countries. Validates:
 *  - Length per country
 *  - Alphanumeric format
 *  - MOD-97 checksum (ISO 7064)
 */
class Iban implements ValidationRule
{
    /**
     * Expected IBAN lengths per country code (ISO 3166-1 alpha-2).
     */
    private const LENGTHS = [
        'AL' => 28, 'AD' => 24, 'AT' => 20, 'AZ' => 28, 'BH' => 22,
        'BY' => 28, 'BE' => 16, 'BA' => 20, 'BR' => 29, 'BG' => 22,
        'CR' => 22, 'HR' => 21, 'CY' => 28, 'CZ' => 24, 'DK' => 18,
        'DO' => 28, 'TL' => 23, 'EG' => 29, 'SV' => 28, 'EE' => 20,
        'FO' => 18, 'FI' => 18, 'FR' => 27, 'GE' => 22, 'DE' => 22,
        'GI' => 23, 'GR' => 27, 'GL' => 18, 'GT' => 28, 'HU' => 28,
        'IS' => 26, 'IQ' => 23, 'IE' => 22, 'IL' => 23, 'IT' => 27,
        'JO' => 30, 'KZ' => 20, 'XK' => 20, 'KW' => 30, 'LV' => 21,
        'LB' => 28, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'MK' => 19,
        'MT' => 31, 'MR' => 27, 'MU' => 30, 'MC' => 27, 'MD' => 24,
        'ME' => 22, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PS' => 29,
        'PL' => 28, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'LC' => 32,
        'SM' => 27, 'ST' => 25, 'SA' => 24, 'RS' => 22, 'SC' => 31,
        'SK' => 24, 'SI' => 19, 'ES' => 24, 'SE' => 24, 'CH' => 21,
        'TN' => 24, 'TR' => 26, 'UA' => 29, 'AE' => 23, 'GB' => 22,
        'VA' => 22, 'VG' => 24,
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) $value));

        if ($iban === '') {
            return; // Allow empty — use 'required' rule separately
        }

        // Must be alphanumeric
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            $fail(__('validation.iban'));

            return;
        }

        $country = substr($iban, 0, 2);

        // Check country-specific length
        if (isset(self::LENGTHS[$country]) && strlen($iban) !== self::LENGTHS[$country]) {
            $fail(__('validation.iban'));

            return;
        }

        // MOD-97 checksum (ISO 7064)
        // Move first 4 chars to end, convert letters to numbers (A=10, B=11, …, Z=35)
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        if (bcmod($numeric, '97') !== '1') {
            $fail(__('validation.iban'));
        }
    }
}
