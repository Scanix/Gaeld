<?php

namespace App\Domains\Banking\Exceptions;

use App\Support\Exceptions\DomainException;

class UnlinkedBankAccountException extends DomainException
{
    public function __construct(string $message = 'Bank account is not linked to a ledger account.')
    {
        parent::__construct($message);
    }
}
