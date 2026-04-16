<?php

namespace App\Domains\Migration\DTOs;

use App\Domains\Migration\Enums\DataType;

/**
 * Result of executing an import for a single data type.
 */
class ImportResult
{
    public function __construct(
        public readonly DataType $dataType,
        public readonly bool $success,
        public readonly int $importedCount = 0,
        public readonly int $skippedCount = 0,
        public readonly int $failedCount = 0,
        /** @var string[] */
        public readonly array $errors = [],
        /** @var string[] */
        public readonly array $warnings = [],
        /** @var array<int|string> IDs of created records for undo support */
        public readonly array $createdIds = [],
    ) {}

    /**
     * @param  string[]  $warnings
     * @param  array<int|string>  $createdIds
     */
    public static function success(DataType $dataType, int $imported, int $skipped = 0, array $warnings = [], array $createdIds = [], int $failed = 0): self
    {
        return new self(
            dataType: $dataType,
            success: true,
            importedCount: $imported,
            skippedCount: $skipped,
            failedCount: $failed,
            warnings: $warnings,
            createdIds: $createdIds,
        );
    }

    /**
     * @param  string[]  $errors
     */
    public static function failure(DataType $dataType, array $errors, int $imported = 0, int $failed = 0): self
    {
        return new self(
            dataType: $dataType,
            success: false,
            importedCount: $imported,
            failedCount: $failed,
            errors: $errors,
        );
    }

    public function totalProcessed(): int
    {
        return $this->importedCount + $this->skippedCount + $this->failedCount;
    }
}
