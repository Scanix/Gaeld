<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
}
