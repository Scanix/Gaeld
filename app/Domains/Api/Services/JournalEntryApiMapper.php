<?php

namespace App\Domains\Api\Services;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;

final class JournalEntryApiMapper
{
    public function __construct(
        private AccountCodeResolver $accountResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function toData(array $validated, string $organizationId): JournalEntryData
    {
        $lines = array_map(
            fn (array $line): JournalLineData => new JournalLineData(
                accountId: (string) $this->accountResolver
                    ->resolve($organizationId, (string) $line['account_code'])
                    ->id,
                debit: (string) $line['debit'],
                credit: (string) $line['credit'],
                description: $line['description'] ?? null,
            ),
            $validated['lines'],
        );

        return new JournalEntryData(
            date: $validated['date'],
            reference: $validated['reference'] ?? null,
            description: $validated['description'] ?? null,
            lines: $lines,
            type: 'api',
        );
    }
}
