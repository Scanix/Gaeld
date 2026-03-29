<?php

namespace App\Domains\Accounting\Exceptions;

use RuntimeException;

/** Thrown when attempting to post an entry to a closed fiscal year. */
class FiscalYearClosedException extends RuntimeException
{
    public function __construct(int $year)
    {
        parent::__construct("Cannot post to closed fiscal year {$year}.");
    }
}
