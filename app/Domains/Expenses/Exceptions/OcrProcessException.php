<?php

namespace App\Domains\Expenses\Exceptions;

use RuntimeException;

class OcrProcessException extends RuntimeException
{
    public static function processFailed(int $exitCode, string $errorOutput): self
    {
        return new self(
            "OCR process failed with exit code {$exitCode}: {$errorOutput}",
            $exitCode,
        );
    }
}
