<?php

namespace App\Domains\Banking\Enums;

enum BankMatchType: string
{
    case QrReference = 'qr_reference';
    case AmountCustomer = 'amount_customer';
    case Heuristic = 'heuristic';
}
