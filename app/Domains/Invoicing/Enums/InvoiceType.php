<?php

namespace App\Domains\Invoicing\Enums;

/** Invoice document type: standard invoice or credit note. */
enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => __('app.invoice_type_invoice'),
            self::CreditNote => __('app.invoice_type_credit_note'),
        };
    }
}
