<?php

namespace App\Domains\Accounting\DTOs;

/**
 * Value object representing a single debit/credit line in a journal entry.
 *
 * Amounts are always strings (bcmath-compatible). Callers must pass
 * string values; use '0' instead of integer 0.
 */
readonly class JournalLineData
{
    public function __construct(
        public string $accountId,
        public string $debit,
        public string $credit,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accountId: $data['account_id'],
            debit: (string) $data['debit'],
            credit: (string) $data['credit'],
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'description' => $this->description,
        ];
    }
}
