<?php

namespace App\Domains\Migration\DTOs;

class JournalEntryImportRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $date,
        public readonly ?string $reference = null,
        public readonly ?string $description = null,
        /** @var array<array{account_code: string, debit: ?string, credit: ?string, description: ?string}> */
        public readonly array $lines = [],
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'reference' => $this->reference,
            'description' => $this->description,
            'lines' => $this->lines,
        ];
    }
}
