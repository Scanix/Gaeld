<?php

namespace App\Domains\Banking\Enums;

/** Confidence level of an automatic bank transaction match (0–100). */
enum MatchConfidence: int
{
    case QrReference = 100;
    case AmountAndCustomer = 90;
    case AutoExpenseThreshold = 80;
    case Heuristic = 70;

    public const AMOUNT_TOLERANCE = '1.00';
}
