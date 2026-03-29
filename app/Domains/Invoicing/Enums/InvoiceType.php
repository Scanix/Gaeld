<?php

namespace App\Domains\Invoicing\Enums;

/** Invoice document type: standard invoice or credit note. */
enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
}
