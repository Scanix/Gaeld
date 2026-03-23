<?php

namespace App\Domains\Banking\Enums;

enum MatchConfidence: int
{
    case QrReference = 100;
    case AmountAndCustomer = 90;
    case AutoExpenseThreshold = 80;
    case Heuristic = 70;

    public const AMOUNT_TOLERANCE = '0.05';
}
