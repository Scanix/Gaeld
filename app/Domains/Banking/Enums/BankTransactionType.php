<?php

namespace App\Domains\Banking\Enums;

enum BankTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
