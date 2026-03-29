<?php

namespace App\Domains\Invoicing\Enums;

/** Payment method used to settle an invoice or expense. */
enum PaymentMethod: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Card = 'card';
}
