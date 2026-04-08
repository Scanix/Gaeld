<?php

namespace App\Domains\Banking\Exceptions;

use App\Support\Exceptions\DomainException;

class ReconciliationFailedException extends DomainException
{
    public function __construct(string $message = 'Reconciliation failed.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
