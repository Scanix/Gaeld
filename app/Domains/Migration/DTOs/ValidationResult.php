<?php

namespace App\Domains\Migration\DTOs;

/**
 * Result of validating parsed rows before import.
 */
class ValidationResult
{
    /**
     * @param  bool  $valid  Whether all rows passed validation
     * @param  array<int, string[]>  $rowErrors  Errors keyed by source row number
     * @param  string[]  $globalErrors  Errors not tied to a specific row
     * @param  array<int, string[]>  $rowWarnings  Warnings keyed by source row number
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $rowErrors = [],
        public readonly array $globalErrors = [],
        public readonly array $rowWarnings = [],
    ) {}

    public static function success(): self
    {
        return new self(valid: true);
    }

    public static function failure(array $globalErrors = [], array $rowErrors = []): self
    {
        return new self(
            valid: false,
            rowErrors: $rowErrors,
            globalErrors: $globalErrors,
        );
    }

    public function totalErrors(): int
    {
        $count = count($this->globalErrors);
        foreach ($this->rowErrors as $errors) {
            $count += count($errors);
        }

        return $count;
    }
}
