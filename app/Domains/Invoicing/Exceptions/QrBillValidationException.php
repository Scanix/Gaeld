<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Support\Exceptions\DomainException;

class QrBillValidationException extends DomainException
{
    /** @param array<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('QR bill data is invalid: '.implode(', ', $violations));
    }
}
