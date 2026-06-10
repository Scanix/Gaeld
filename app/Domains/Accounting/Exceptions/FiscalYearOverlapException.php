<?php

namespace App\Domains\Accounting\Exceptions;

use DomainException;

/** Thrown when a proposed fiscal year overlaps an existing one for the org. */
class FiscalYearOverlapException extends DomainException
{
    public function __construct(string $startDate, string $endDate)
    {
        parent::__construct(__('app.fiscal_year_overlap', [
            'start' => $startDate,
            'end' => $endDate,
        ]));
    }
}
