<?php

namespace App\Domains\Invoicing\Enums;

enum PaymentMethod: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Card = 'card';
}
