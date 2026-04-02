<?php

namespace Tests\Traits;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;

/**
 * Helpers for creating accounting fixtures in Feature tests.
 *
 * Expects $this->organization (or $this->org) to be set.
 */
trait CreatesAccountingFixtures
{
    protected function postJournalEntry(string $date, array $lines, ?string $reference = null, string $description = 'Test entry'): void
    {
        $orgId = $this->organization->id ?? $this->org->id;

        app(LedgerService::class)->postEntry($orgId, new JournalEntryData(
            date: $date,
            reference: $reference ?? 'JE-'.uniqid(),
            description: $description,
            lines: $lines,
        ));
    }

    protected function journalLine(Account $account, string $debit, string $credit, string $description = ''): JournalLineData
    {
        return new JournalLineData(
            accountId: $account->id,
            debit: $debit,
            credit: $credit,
            description: $description,
        );
    }
}
