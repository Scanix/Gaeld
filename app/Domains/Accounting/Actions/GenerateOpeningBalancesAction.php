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

            if (bccomp($balance, '0', 2) === 0) {
                continue;
            }

            $isDebitNormal = $account->type->isDebitNormal();

            if (bccomp($balance, '0', 2) > 0) {
                // Positive balance: debit for debit-normal, credit for credit-normal
                if ($isDebitNormal) {
                    $lines[] = new JournalLineData(
                        accountId: (string) $account->id,
                        debit: $balance,
                        credit: '0',
                        description: "Solde d'ouverture {$nextYear} — {$account->code}",
                    );
                    $totalDebit = bcadd($totalDebit, $balance, 2);
                } else {
                    $lines[] = new JournalLineData(
                        accountId: (string) $account->id,
                        debit: '0',
                        credit: $balance,
                        description: "Solde d'ouverture {$nextYear} — {$account->code}",
                    );
                    $totalCredit = bcadd($totalCredit, $balance, 2);
                }
            } else {
                // Negative balance (rare but possible): reverse
                $absBalance = bcmul($balance, '-1', 2);
                if ($isDebitNormal) {
                    $lines[] = new JournalLineData(
                        accountId: (string) $account->id,
                        debit: '0',
                        credit: $absBalance,
                        description: "Solde d'ouverture {$nextYear} — {$account->code}",
                    );
                    $totalCredit = bcadd($totalCredit, $absBalance, 2);
                } else {
                    $lines[] = new JournalLineData(
                        accountId: (string) $account->id,
                        debit: $absBalance,
                        credit: '0',
                        description: "Solde d'ouverture {$nextYear} — {$account->code}",
                    );
                    $totalDebit = bcadd($totalDebit, $absBalance, 2);
                }
            }
        }

        if (empty($lines)) {
            return null;
        }

        // Balance the entry via the opening balance (9000) account
        $diff = bcsub($totalDebit, $totalCredit, 2);

        if (bccomp($diff, '0', 2) > 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: '0',
                credit: $diff,
                description: "Solde d'ouverture {$nextYear} — contrepartie",
            );
        } elseif (bccomp($diff, '0', 2) < 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $openingAccount->id,
                debit: bcmul($diff, '-1', 2),
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
            ? bcsub($debits, $credits, 2)
            : bcsub($credits, $debits, 2);
    }
}
