<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Support\Money;

/**
 * Generates opening balance journal entries for a new fiscal year
 * based on the balance sheet accounts from the previous year.
 *
 * Only balance sheet accounts (Asset, Liability, Equity) carry forward;
 * P&L accounts (Revenue, Expense) were zeroed by YearEndClosingAction.
 */
class GenerateOpeningBalancesAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LedgerQueryService $ledgerQuery,
    ) {}

    /**
     * @param  string  $orgId  Organization UUID
     * @param  int  $closedYear  The calendar year that was just closed (e.g. 2025)
     * @param  FiscalYear|null  $closedFiscalYear  The explicit fiscal period when available
     */
    public function execute(string $orgId, int $closedYear, ?FiscalYear $closedFiscalYear = null): ?JournalEntry
    {
        $openingDate = $closedFiscalYear?->end_date
            ->copy()
            ->addDay()
            ->toDateString()
            ?? sprintf('%d-01-01', $closedYear + 1);
        $nextYear = (int) substr($openingDate, 0, 4);
        $asOfDate = $closedFiscalYear?->end_date->toDateString()
            ?? sprintf('%d-12-31', $closedYear);

        $balanceSheetTypes = [
            AccountType::Asset->value,
            AccountType::Liability->value,
            AccountType::Equity->value,
        ];

        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereIn('type', $balanceSheetTypes)
            ->orderBy('code')
            ->get();

        $openingAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::OPENING_BALANCE);

        $lines = [];
        /** @var array<string, string> $desiredBalances */
        $desiredBalances = [];
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($accounts as $account) {
            // Skip the opening balance account itself
            if ($account->code === AccountCode::OPENING_BALANCE) {
                continue;
            }

            $balance = $this->computeBalance($account, $asOfDate);
            $desiredBalances['account:'.(string) $account->id] = $balance;

            if (Money::isZero($balance)) {
                continue;
            }

            $isDebitNormal = $account->type->isDebitNormal();
            $isPositive = Money::isPositive($balance);
            $absBalance = $isPositive ? $balance : Money::negate($balance);

            // Debit when positive+debit-normal or negative+credit-normal
            $shouldDebit = $isPositive === $isDebitNormal;

            $lines[] = new JournalLineData(
                accountId: (string) $account->id,
                debit: $shouldDebit ? $absBalance : '0',
                credit: $shouldDebit ? '0' : $absBalance,
                description: "Solde d'ouverture {$nextYear} — {$account->code}",
            );

            if ($shouldDebit) {
                $totalDebit = Money::add($totalDebit, $absBalance);
            } else {
                $totalCredit = Money::add($totalCredit, $absBalance);
            }
        }

        if (empty($lines)) {
            return null;
        }

        $nextFiscalYear = $closedFiscalYear !== null
            ? FiscalYear::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->whereDate('start_date', $openingDate)
                ->first()
            : null;
        $existingOpeningEntries = $closedFiscalYear !== null
            ? JournalEntry::query()
                ->where('organization_id', $orgId)
                ->where('is_posted', true)
                ->where(function ($query) use ($nextYear): void {
                    $query->where('reference', "OPENING-{$nextYear}")
                        ->orWhere('reference', 'like', "OPENING-{$nextYear}-v%");
                })
                ->with('lines')
                ->get()
            : collect();

        if ($closedFiscalYear !== null && ($nextFiscalYear?->isClosed() || $existingOpeningEntries->isNotEmpty())) {
            return $this->postOpeningRestatement(
                orgId: $orgId,
                nextYear: $nextYear,
                desiredBalances: $desiredBalances,
                accounts: $accounts,
                openingAccount: $openingAccount,
                existingOpeningEntries: $existingOpeningEntries,
                nextFiscalYear: $nextFiscalYear,
                openingDate: $openingDate,
            );
        }

        // Balance the entry via the opening balance (9000) account
        $diff = Money::subtract($totalDebit, $totalCredit);

        if (Money::isPositive($diff)) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: '0',
                credit: $diff,
                description: "Solde d'ouverture {$nextYear} — contrepartie",
            );
        } elseif (Money::isNegative($diff)) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: Money::negate($diff),
                credit: '0',
                description: "Solde d'ouverture {$nextYear} — contrepartie",
            );
        }

        return $this->ledger->postEntry($orgId, new JournalEntryData(
            date: $openingDate,
            reference: "OPENING-{$nextYear}",
            description: "Bilan d'ouverture {$nextYear}",
            lines: $lines,
        ));
    }

    /**
     * Post only the balance-sheet delta when a prior opening entry already
     * exists. This keeps closed successor years immutable during a restatement.
     *
     * @param  array<string, string>  $desiredBalances
     * @param  iterable<Account>  $accounts
     * @param  iterable<JournalEntry>  $existingOpeningEntries
     */
    private function postOpeningRestatement(
        string $orgId,
        int $nextYear,
        array $desiredBalances,
        iterable $accounts,
        Account $openingAccount,
        iterable $existingOpeningEntries,
        ?FiscalYear $nextFiscalYear,
        string $openingDate,
    ): ?JournalEntry {
        /** @var array<string, string> $effectiveBalances */
        $effectiveBalances = [];
        $accountsById = collect($accounts)->keyBy(fn (Account $account): string => (string) $account->id);
        $existingRestatements = JournalEntry::query()
            ->where('organization_id', $orgId)
            ->where('is_posted', true)
            ->where('reference', 'like', 'OPENING-RESTATEMENT-%')
            ->with('lines')
            ->get();
        $allOpeningEntries = collect($existingOpeningEntries)->concat($existingRestatements);

        foreach ($allOpeningEntries as $openingEntry) {
            foreach ($openingEntry->lines as $line) {
                $accountId = (string) $line->account_id;
                $account = $accountsById->get($accountId);

                if (! $account instanceof Account) {
                    continue;
                }

                $lineBalance = $account->type->isDebitNormal()
                    ? Money::subtract((string) $line->debit, (string) $line->credit)
                    : Money::subtract((string) $line->credit, (string) $line->debit);
                $accountKey = 'account:'.$accountId;
                $effectiveBalances[$accountKey] = Money::add($effectiveBalances[$accountKey] ?? '0', $lineBalance);
            }
        }

        $lines = [];
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($accounts as $account) {
            $accountId = (string) $account->id;
            if ($account->code === AccountCode::OPENING_BALANCE) {
                continue;
            }

            $accountKey = 'account:'.$accountId;
            $delta = Money::subtract(
                $desiredBalances[$accountKey] ?? '0',
                $effectiveBalances[$accountKey] ?? '0',
            );

            if (Money::isZero($delta)) {
                continue;
            }

            $isPositive = Money::isPositive($delta);
            $absDelta = $isPositive ? $delta : Money::negate($delta);
            $shouldDebit = $isPositive === $account->type->isDebitNormal();

            $lines[] = new JournalLineData(
                accountId: $accountId,
                debit: $shouldDebit ? $absDelta : '0',
                credit: $shouldDebit ? '0' : $absDelta,
                description: "Restatement du solde d'ouverture {$nextYear} — {$account->code}",
            );

            if ($shouldDebit) {
                $totalDebit = Money::add($totalDebit, $absDelta);
            } else {
                $totalCredit = Money::add($totalCredit, $absDelta);
            }
        }

        if ($lines === []) {
            return null;
        }

        $diff = Money::subtract($totalDebit, $totalCredit);
        if (Money::isPositive($diff)) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: '0',
                credit: $diff,
                description: "Restatement du solde d'ouverture {$nextYear} — contrepartie",
            );
        } elseif (Money::isNegative($diff)) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: Money::negate($diff),
                credit: '0',
                description: "Restatement du solde d'ouverture {$nextYear} — contrepartie",
            );
        }

        $targetFiscalYear = $nextFiscalYear?->isClosed()
            ? FiscalYear::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->whereDate('start_date', '>=', $openingDate)
                ->where('status', '!=', FiscalYearStatus::Closed->value)
                ->orderBy('start_date')
                ->first()
            : $nextFiscalYear;

        if ($targetFiscalYear === null) {
            throw new \RuntimeException('No open fiscal year is available for the opening-balance restatement.');
        }

        $targetYear = $targetFiscalYear->start_date->year;
        $baseReference = "OPENING-RESTATEMENT-{$targetYear}";
        $reference = $baseReference;
        $version = 2;
        while (JournalEntry::query()->where('organization_id', $orgId)->where('reference', $reference)->exists()) {
            $reference = "{$baseReference}-v{$version}";
            $version++;
        }

        return $this->ledger->postEntry($orgId, new JournalEntryData(
            date: $targetFiscalYear->start_date->toDateString(),
            reference: $reference,
            description: "Restatement des soldes d'ouverture {$targetYear}",
            lines: $lines,
        ), $targetFiscalYear->id);
    }

    private function computeBalance(Account $account, string $asOfDate): string
    {
        $query = TransactionLine::where('account_id', $account->id)
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('is_posted', true)
                ->where('date', '<=', $asOfDate)
            );

        $debits = (string) (clone $query)->sum('debit');
        $credits = (string) (clone $query)->sum('credit');

        return $account->type->isDebitNormal()
            ? Money::subtract($debits, $credits)
            : Money::subtract($credits, $debits);
    }
}
