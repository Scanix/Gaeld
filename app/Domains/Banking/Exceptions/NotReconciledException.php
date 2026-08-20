<?php

namespace App\Domains\Banking\Exceptions;

use App\Support\Exceptions\DomainException;

class NotReconciledException extends DomainException
{
    public function __construct(string $message = 'Transaction is not reconciled.')
    {
        parent::__construct($message);
    }
}
