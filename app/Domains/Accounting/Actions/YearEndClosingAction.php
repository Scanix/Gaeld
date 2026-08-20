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
use Carbon\Carbon;
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

        $unsettled = $this->getUnsettledVatPeriods($orgId, $from, $to);
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

        // The entire closing workflow must be atomic: posting the closing
        // entry, locking the fiscal year, archiving documents, and
        // generating the next year's opening balances are all part of one
        // logical operation. If any later step fails (e.g. a duplicate
        // "OPENING-{year}" reference when re-closing after a reopen), every
        // earlier step must roll back too — otherwise the org is left with
        // a posted closing entry and a locked fiscal year but no opening
        // balances, which is an inconsistent, hard-to-recover state.
        $nextYearCreated = false;

        DB::transaction(function () use (
            $income,
            $expenses,
            $year,
            $validated,
            $resultAccount,
            $orgId,
            $org,
            $fiscalYear,
            $actingUser,
            &$nextYearCreated,
        ): void {
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

            $org->closeFiscalYear($year);

            if ($fiscalYear !== null) {
                $nextYearCreated = $this->fiscalYears->close($fiscalYear, $actingUser);
            }

            $this->archiving->archiveFiscalYear($orgId, $year, $fiscalYear?->id);
            $this->openingBalances->execute($orgId, $year);
        });

        Log::info('Year-end closing completed', [
            'organization_id' => $orgId,
            'fiscal_year' => $year,
            'fiscal_year_id' => $fiscalYear?->id,
            'from_date' => $from,
            'to_date' => $to,
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
    private function getUnsettledVatPeriods(string $orgId, string $fromDate, string $toDate): array
    {
        $unsettled = [];
        $periodStart = Carbon::parse($fromDate)->startOfQuarter();
        $periodEnd = Carbon::parse($toDate)->endOfQuarter();

        for ($quarterStart = $periodStart; $quarterStart->lte($periodEnd); $quarterStart->addQuarter()) {
            $from = $quarterStart->toDateString();
            $to = $quarterStart->copy()->endOfQuarter()->toDateString();
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
                $unsettled[] = 'Q'.$quarterStart->quarter.' '.$quarterStart->year;
            }
        }

        return $unsettled;
    }
}
