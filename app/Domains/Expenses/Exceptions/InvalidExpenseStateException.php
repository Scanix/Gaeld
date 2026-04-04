<?php

namespace App\Domains\Expenses\Exceptions;

use App\Support\Exceptions\DomainException;

class InvalidExpenseStateException extends DomainException
{
    public function __construct(string $message = 'Expense is in an invalid state for this operation.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
