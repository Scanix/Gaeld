<?php

namespace App\Domains\Accounting\Exceptions;

use App\Support\Exceptions\DomainException;

class AlreadyPostedException extends DomainException
{
    public function __construct(string $message = 'Journal entry has already been posted.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
