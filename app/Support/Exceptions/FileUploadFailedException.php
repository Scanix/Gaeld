<?php

namespace App\Support\Exceptions;

/**
 * Thrown when a file could not be written to the target storage disk
 * (disk full, permissions, unreachable cloud storage, ...).
 */
class FileUploadFailedException extends DomainException
{
    public function __construct(string $message = 'The file could not be stored.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
