<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Services\ClosingAccountsService;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performs the full fiscal year-end closing workflow: resolves the fiscal
 * year, validates business rules, posts the closing journal entry, locks
 * the fiscal year, archives documents, and generates opening balances.
 */
class YearEndClosingAction
{
    public function __construct(
        private readonly ClosingAccountsService $closingAccounts,
        private readonly FiscalYearService $fiscalYears,
        private readonly LedgerService $ledger,
        private readonly LegalArchivingService $archiving,
        private readonly GenerateOpeningBalancesAction $openingBalances,
    ) {}

    /**
     * Execute the year-end closing for the given organisation.
     *
     * @param  array<string, mixed>  $validated  Validated fields from StoreYearEndClosingRequest
     *
     * @throws \RuntimeException when business rules prevent closing
     */
    public function execute(Organization $org, array $validated, User $actingUser): bool
    {
        $orgId = $org->id;
        $year = (int) $validated['year'];

        $fiscalYear = $this->resolveFiscalYear($orgId, $year, $validated['fiscal_year_id'] ?? null);

        if ($fiscalYear !== null) {
            $year = (int) $fiscalYear->start_date->year;
            $from = $fiscalYear->start_date->toDateString();
            $to = $fiscalYear->end_date->toDateString();
        } else {
            $from = "{$year}-01-01";
            $to = "{$year}-12-31";
        }

        [$income, $expenses] = $this->closingAccounts->compute($orgId, $from, $to);

        if (empty(array_merge($income, $expenses))) {
            throw new \RuntimeException('No accounts to close for this period.');
        }

        $unsettled = $this->getUnsettledVatPeriods($orgId, $year);
        if (! empty($unsettled)) {
            throw new \RuntimeException(__('app.fiscal_year_unsettled_vat', [
                'year' => $year,
                'periods' => implode(', ', $unsettled),
            ]));
        }

        $resultAccount = Account::where('organization_id', $orgId)
            ->where('code', $validated['result_account_code'])
            ->first();

        if (! $resultAccount) {
            throw new \RuntimeException("Account '{$validated['result_account_code']}' not found.");
        }

        DB::transaction(function () use ($income, $expenses, $year, $validated, $resultAccount, $orgId): void {
            $lines = [];
            $netDebitOnResult = '0';
            $netCreditOnResult = '0';

            foreach ($income as $row) {
                if (Money::isZero((string) $row['balance'])) {
                    continue;
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $row['account_id'],
                    debit: (string) $row['balance'],
                    credit: '0',
                    description: __('app.closing_line_description', ['year' => $year, 'code' => $row['code']]),
                );
                $netCreditOnResult = Money::add($netCreditOnResult, (string) $row['balance']);
            }

            foreach ($expenses as $row) {
                if (Money::isZero((string) $row['balance'])) {
                    continue;
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $row['account_id'],
                    debit: '0',
                    credit: (string) $row['balance'],
                    description: __('app.closing_line_description', ['year' => $year, 'code' => $row['code']]),
                );
                $netDebitOnResult = Money::add($netDebitOnResult, (string) $row['balance']);
            }

            $lines[] = new JournalLineData(
                accountId: (string) $resultAccount->id,
                debit: $netDebitOnResult,
                credit: $netCreditOnResult,
                description: __('app.closing_result_description', ['year' => $year]),
            );

            $entry = new JournalEntryData(
                date: $validated['closing_date'],
                reference: $validated['reference'],
                description: __('app.closing_entry_description', ['year' => $year]),
                lines: $lines,
            );

            $journalEntry = $this->ledger->postEntry($orgId, $entry);
            $journalEntry->update(['type' => 'year_end_closing']);
        });

        $org->closeFiscalYear($year);

        $nextYearCreated = false;
        if ($fiscalYear !== null) {
            $nextYearCreated = $this->fiscalYears->close($fiscalYear, $actingUser);
        }

        $this->archiving->archiveFiscalYear($orgId, $year);
        $this->openingBalances->execute($orgId, $year);

        Log::info('Year-end closing completed', [
            'organization_id' => $orgId,
            'fiscal_year' => $year,
            'closing_date' => $validated['closing_date'],
            'reference' => $validated['reference'],
            'revenue_accounts_closed' => count($income),
            'expense_accounts_closed' => count($expenses),
        ]);

        return $nextYearCreated;
    }

    private function resolveFiscalYear(string $orgId, int $year, ?string $fiscalYearId): ?FiscalYear
    {
        if ($fiscalYearId !== null) {
            $fy = FiscalYear::query()
                ->where('organization_id', $orgId)
                ->where('id', $fiscalYearId)
                ->first();
            if ($fy !== null) {
                return $fy;
            }
        }

        return FiscalYear::query()
            ->where('organization_id', $orgId)
            ->whereYear('start_date', $year)
            ->first();
    }

    /**
     * Return quarter labels (e.g. "Q1", "Q2") for which no VAT settlement
     * journal entry exists in the given year.
     *
     * @return string[]
     */
    private function getUnsettledVatPeriods(string $orgId, int $year): array
    {
        $quarters = [
            1 => ["{$year}-01-01", "{$year}-03-31"],
            2 => ["{$year}-04-01", "{$year}-06-30"],
            3 => ["{$year}-07-01", "{$year}-09-30"],
            4 => ["{$year}-10-01", "{$year}-12-31"],
        ];

        $unsettled = [];

        foreach ($quarters as $q => [$from, $to]) {
            $hasVatActivity = VatEntry::query()
                ->whereHas('journalEntry', fn ($jq) => $jq
                    ->where('organization_id', $orgId)
                    ->whereBetween('date', [$from, $to])
                )
                ->exists();

            if (! $hasVatActivity) {
                continue;
            }

            $exists = JournalEntry::where('organization_id', $orgId)
                ->where('type', 'vat_settlement')
                ->where('reference', "VAT-SETTLEMENT-{$from}-{$to}")
                ->exists();

            if (! $exists) {
                $unsettled[] = "Q{$q}";
            }
        }

        return $unsettled;
    }
}
