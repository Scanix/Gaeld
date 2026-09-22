<?php

namespace App\Domains\Expenses\Enums;

enum ExpenseAmountBasis: string
{
    case Gross = 'gross';
    case Net = 'net';
}
