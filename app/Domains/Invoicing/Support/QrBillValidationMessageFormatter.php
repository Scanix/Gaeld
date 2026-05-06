<?php

namespace App\Domains\Invoicing\Support;

final class QrBillValidationMessageFormatter
{
    /**
     * @param  array<string>  $violations
     */
    public function format(array $violations): string
    {
        $categories = $this->categorize($violations);

        if ($categories === []) {
            $message = (string) __('app.qr_invoice_error_generic');
        } else {
            $details = implode(', ', array_map(
                fn (string $category): string => (string) __('app.qr_invoice_error_detail_'.$category),
                $categories,
            ));

            $message = (string) __('app.qr_invoice_error_summary', ['details' => $details]);

            if (in_array('qr_iban', $categories, true)) {
                $message .= ' '.(string) __('app.qr_iban_help_where_to_find');
            }
        }

        // Append the underlying technical violations so the user can pinpoint
        // exactly which field failed validation. Without this, the formatted
        // summary alone is too vague to act on.
        $rawDetails = $this->joinViolations($violations);
        if ($rawDetails !== '') {
            $message .= ' — '.(string) __('app.qr_invoice_error_details_label').' '.$rawDetails;
        }

        return $message;
    }

    /**
     * @param  array<string>  $violations
     */
    private function joinViolations(array $violations): string
    {
        $clean = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $violations,
        ))));

        return implode(' ; ', $clean);
    }

    /**
     * @param  array<string>  $violations
     * @return array<string>
     */
    private function categorize(array $violations): array
    {
        $categories = [];

        foreach ($violations as $violation) {
            $normalized = mb_strtolower($violation);

            if ($this->containsAny($normalized, ['qr-iban', 'qr iban', 'iid'])) {
                $categories['qr_iban'] = true;

                continue;
            }

            if ($this->containsAny($normalized, ['creditor', 'iban', 'account'])) {
                $categories['creditor'] = true;

                continue;
            }

            if ($this->containsAny($normalized, ['debtor', 'address', 'zip', 'city', 'country'])) {
                $categories['customer'] = true;

                continue;
            }

            if ($this->containsAny($normalized, ['amount', 'currency'])) {
                $categories['amount'] = true;

                continue;
            }

            if ($this->containsAny($normalized, ['reference', 'qrr', 'scor'])) {
                $categories['reference'] = true;
            }
        }

        return array_keys($categories);
    }

    /**
     * @param  array<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
