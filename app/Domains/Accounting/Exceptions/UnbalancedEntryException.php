<?php

namespace App\Domains\Accounting\Exceptions;

use App\Support\Exceptions\DomainException;

class UnbalancedEntryException extends DomainException
{
    public function __construct(string $message = 'Journal entry is not balanced.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
