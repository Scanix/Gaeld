<?php

namespace App\Domains\Migration\DTOs;

use App\Domains\Migration\Contracts\ImportRowInterface;

/**
 * Base import row providing common fields for all data types.
 */
abstract class AbstractImportRow implements ImportRowInterface
{
    /** @var string[] */
    protected array $warnings = [];

    protected bool $valid = true;

    public function __construct(
        protected readonly int $sourceRow,
    ) {}

    public function sourceRow(): int
    {
        return $this->sourceRow;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function markInvalid(): void
    {
        $this->valid = false;
    }
}
