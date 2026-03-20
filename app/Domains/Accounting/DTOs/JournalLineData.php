<?php

namespace App\Domains\Accounting\DTOs;

/**
 * Value object representing a single debit/credit line in a journal entry.
 *
 * Amounts accept string, int, or float. Callers that come from bcmath
 * will pass strings; callers that pass literal zero use int — both are
 * accepted by the underlying DB decimal columns.
 */
readonly class JournalLineData
{
    public function __construct(
        public string $accountId,
        public string|int|float $debit,
        public string|int|float $credit,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accountId: $data['account_id'],
            debit: $data['debit'],
            credit: $data['credit'],
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
