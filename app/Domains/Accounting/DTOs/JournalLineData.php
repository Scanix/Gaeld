<?php

namespace App\Domains\Accounting\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * Value object representing a single debit/credit line in a journal entry.
 *
 * Amounts are always strings (bcmath-compatible). Callers must pass
 * string values; use '0' instead of integer 0.
 */
readonly class JournalLineData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $accountId,
        public string $debit,
        public string $credit,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['account_id', 'debit', 'credit']);

        return new self(
            accountId: $data['account_id'],
            debit: (string) $data['debit'],
            credit: (string) $data['credit'],
            description: $data['description'] ?? null,
        );
    }

}
