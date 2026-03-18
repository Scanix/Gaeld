<?php

namespace App\Domains\Banking\Exceptions;

use DomainException;

class AlreadyReconciledException extends DomainException
{
    public function __construct(string $message = 'Transaction is already reconciled.')
    {
        parent::__construct($message);
    }
}
