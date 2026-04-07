<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * Validate a QR-IBAN: must be a valid Swiss/LI IBAN with IID in range 30000–31999.
 */
class QrIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = strtoupper(preg_replace('/\s+/', '', (string) $value));

        if ($iban === '') {
            return; // Allow empty — use 'required' rule separately
        }

        // First validate as a regular IBAN
        $v = Validator::make([$attribute => $value], [$attribute => [new Iban]]);
        if ($v->fails()) {
            $fail(__('validation.iban'));

            return;
        }

        // Must be CH or LI
        if (! preg_match('/^(CH|LI)/', $iban)) {
            $fail(__('validation.qr_iban_swiss_only'));

            return;
        }

        // IID (positions 5-9) must be in QR range 30000–31999
        $iid = (int) substr($iban, 4, 5);
        if ($iid < 30000 || $iid > 31999) {
            $fail(__('validation.qr_iban'));
        }
    }
}
