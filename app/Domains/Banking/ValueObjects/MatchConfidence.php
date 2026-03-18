<?php

namespace App\Domains\Banking\ValueObjects;

enum MatchConfidence: int
{
    case QrReference = 100;
    case AmountAndCustomer = 90;
    case AutoExpenseThreshold = 80;
    case Heuristic = 70;

    /** Tolerance (±) for fuzzy amount matching, in base currency units. */
    public const AMOUNT_TOLERANCE = '0.05';
}
