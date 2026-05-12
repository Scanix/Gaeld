<?php

namespace App\Domains\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when a proposed fiscal year exceeds the Swiss legal maximum
 * of 23 months (e.g. for a long first year after incorporation).
 */
class FiscalYearTooLongException extends DomainException
{
    public function __construct(public readonly int $months)
    {
        parent::__construct(__('app.fiscal_year_too_long', [
            'months' => $months,
            'max' => 23,
        ]));
    }
}
