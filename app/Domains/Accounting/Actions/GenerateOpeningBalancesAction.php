<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
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
     * @param  int  $closedYear  The year that was just closed (e.g. 2025)
     */
    public function execute(string $orgId, int $closedYear): ?JournalEntry
    {
        $nextYear = $closedYear + 1;
        $openingDate = sprintf('%d-01-01', $nextYear);
        $asOfDate = sprintf('%d-12-31', $closedYear);

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
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($accounts as $account) {
            // Skip the opening balance account itself
            if ($account->code === AccountCode::OPENING_BALANCE) {
                continue;
            }

            $balance = $this->computeBalance($account, $asOfDate);

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
