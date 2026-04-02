<?php

namespace App\Domains\Accounting\Exceptions;

use DomainException;

/** Thrown when attempting to post an entry to a closed fiscal year. */
class FiscalYearClosedException extends DomainException
{
    public function __construct(public readonly int $year)
    {
        parent::__construct(__('app.fiscal_year_closed', ['year' => $year]));
    }
}
