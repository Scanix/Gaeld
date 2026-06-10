<?php

namespace App\Domains\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when a fiscal year operation requires an end_date strictly
 * after start_date (or any other invalid date range).
 */
class InvalidFiscalYearRangeException extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('app.fiscal_year_invalid_range'));
    }
}
