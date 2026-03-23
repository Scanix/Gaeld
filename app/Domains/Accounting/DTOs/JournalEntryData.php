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

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            reference: $data['reference'] ?? null,
            description: $data['description'] ?? null,
            lines: array_map(
                fn (array $line) => JournalLineData::fromArray($line),
                $data['lines'],
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'reference' => $this->reference,
            'description' => $this->description,
            'lines' => array_map(fn (JournalLineData $line) => $line->toArray(), $this->lines),
        ];
    }
}
