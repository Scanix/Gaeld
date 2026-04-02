<?php

namespace App\Domains\Migration\DTOs;

use App\Domains\Migration\Contracts\ImportRowInterface;
use Illuminate\Support\Collection;

/**
 * Preview data for a parsed data type before import.
 */
class PreviewData
{
    /**
     * @param  Collection<int, ImportRowInterface>  $sampleRows  First N rows for preview
     * @param  array<int, string[]>  $rowErrors
     * @param  array<string, array{source_code: string, source_name: string, target_code: ?string, target_name: ?string, confidence: float}>  $accountMappings  Suggested account mappings
     */
    public function __construct(
        public readonly Collection $sampleRows,
        public readonly int $totalRows,
        public readonly int $validRows,
        public readonly int $invalidRows,
        public readonly array $rowErrors = [],
        public readonly array $accountMappings = [],
    ) {}
}
