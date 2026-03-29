<?php

namespace App\Domains\Banking\Enums;

/** Direction of a bank transaction: debit (outgoing) or credit (incoming). */
enum BankTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
