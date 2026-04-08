<?php

namespace App\Domains\Accounting\Exceptions;

use App\Support\Exceptions\DomainException;

class InvalidEntryDataException extends DomainException
{
    public function __construct(string $message = 'Invalid journal entry data.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
