<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Posts a journal entry for Swiss social charges (AVS/AI/APG) on independent income.
 */
final class PostSocialChargesAction
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly LedgerQueryService $ledgerQuery,
    ) {}

    public function execute(string $organizationId, string $amount, string $description, ?string $date = null): JournalEntry
    {
        $socialAccount = $this->ledgerQuery->resolveAccount($organizationId, AccountCode::SOCIAL_CHARGES);
        $bankAccount = $this->ledgerQuery->resolveAccount($organizationId, AccountCode::BANK_CASH);

        $entry = new JournalEntryData(
            date: $date ?? now()->format('Y-m-d'),
            reference: null,
            description: $description,
            lines: [
                new JournalLineData(
                    accountId: (string) $socialAccount->id,
                    debit: $amount,
                    credit: '0.00',
                    description: $description,
                ),
                new JournalLineData(
                    accountId: (string) $bankAccount->id,
                    debit: '0.00',
                    credit: $amount,
                    description: $description,
                ),
            ],
        );

        return DB::transaction(function () use ($organizationId, $entry): JournalEntry {
            $journalEntry = $this->ledgerService->postEntry($organizationId, $entry);

            $journalEntry->update(['type' => 'social_charges']);

            return $journalEntry;
        });
    }
}
