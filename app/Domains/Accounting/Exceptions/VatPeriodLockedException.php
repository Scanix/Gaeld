<?php

namespace App\Domains\Accounting\Exceptions;

use DomainException;

class VatPeriodLockedException extends DomainException
{
    public function __construct(public readonly string $date)
    {
        parent::__construct(__('app.vat_period_locked'));
    }
}
