<?php

namespace App\Domains\Accounting\DTOs;

/**
 * Value object bundling a journal entry header with its balanced lines.
 *
 * Passed to LedgerService::postEntry() and LedgerService::createDraft()
 * as a single typed contract replacing raw associative arrays.
 */
readonly class JournalEntryData
{
    /**
     * @param  JournalLineData[]  $lines  At least two lines; debits must equal credits.
     */
    public function __construct(
        public string $date,
        public ?string $reference,
        public ?string $description,
        public array $lines,
    ) {}
}
