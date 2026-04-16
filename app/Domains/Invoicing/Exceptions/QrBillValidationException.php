<?php

namespace App\Domains\Invoicing\Exceptions;

use App\Support\Exceptions\DomainException;

class QrBillValidationException extends DomainException
{
    /** @param array<string> $violations */
    public function __construct(public readonly array $violations)
    {
        $detail = implode('; ', $violations);
        parent::__construct(
            __('app.qr_bill_invalid_message', ['detail' => $detail])
        );
    }
}
