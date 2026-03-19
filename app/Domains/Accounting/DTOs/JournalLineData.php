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
}
