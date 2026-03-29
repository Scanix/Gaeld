<?php

namespace App\Domains\Banking\Enums;

/** How a bank transaction was matched to a document (QR, amount, manual, etc.). */
enum BankMatchType: string
{
    case QrReference = 'qr_reference';
    case AmountCustomer = 'amount_customer';
    case Heuristic = 'heuristic';
}
