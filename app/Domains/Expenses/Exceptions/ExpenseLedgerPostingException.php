<?php

namespace App\Domains\Expenses\Exceptions;

use App\Support\Exceptions\DomainException;

class ExpenseLedgerPostingException extends DomainException
{
    public function __construct(string $message = 'Failed to post expense to ledger.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
