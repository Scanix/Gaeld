<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Support\Exceptions\DomainException;

class InvalidInvoiceStateException extends DomainException
{
    public function __construct(string $message = 'Invoice is in an invalid state for this operation.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
