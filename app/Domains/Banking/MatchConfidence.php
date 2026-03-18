<?php

namespace App\Domains\Banking;

final class MatchConfidence
{
    public const QR_REFERENCE = 100;
    public const AMOUNT_AND_CLIENT = 90;
    public const AUTO_EXPENSE_THRESHOLD = 80;
    public const HEURISTIC = 70;

    /** Tolerance (±) for fuzzy amount matching, in base currency units. */
    public const AMOUNT_TOLERANCE = '0.05';
}
