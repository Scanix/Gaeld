<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Support\Exceptions\DomainException;

class InvalidPaymentException extends DomainException
{
    public function __construct(string $message = 'Invalid payment operation.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
