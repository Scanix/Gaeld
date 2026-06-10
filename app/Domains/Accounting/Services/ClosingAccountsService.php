<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\TransactionLine;
use App\Support\Money;

/**
 * Computes P&L account balances for a given period, used by
 * the year-end closing process to know amounts to zero out.
 *
 * Revenue accounts are credit-normal: balance = credits − debits.
 * Expense accounts are debit-normal:  balance = debits  − credits.
 * Accounts with a zero net balance are excluded (already closed or untouched).
 */
class ClosingAccountsService
{
    /**
     * Compute income and expense account balances for a date range.
     *
     * @return array{
     *   0: list<array{account_id: int, code: string, name: string, balance: string}>,
     *   1: list<array{account_id: int, code: string, name: string, balance: string}>,
     *   2: string
     * }
     */
    public function compute(string $orgId, string $from, string $to): array
    {
        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereIn('type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->orderBy('code')
            ->get();

        $income = [];
        $expenses = [];
        $net = '0';

        foreach ($accounts as $account) {
            $query = TransactionLine::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q
                    ->where('is_posted', true)
                    ->where('date', '>=', $from)
                    ->where('date', '<=', $to)
                    ->where(fn ($q2) => $q2->whereNull('type')->orWhereNot('type', 'historical_summary'))
                );

            $debits = (string) (clone $query)->sum('debit');
            $credits = (string) (clone $query)->sum('credit');

            $isDebitNormal = $account->type->isDebitNormal();
            $balance = $isDebitNormal
                ? Money::subtract($debits, $credits)
                : Money::subtract($credits, $debits);

            if (Money::isZero($balance)) {
                continue;
            }

            $row = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $balance,
            ];

            if ($account->type === AccountType::Revenue) {
                $income[] = $row;
                $net = Money::add($net, $balance);
            } else {
                $expenses[] = $row;
                $net = Money::subtract($net, $balance);
            }
        }

        return [$income, $expenses, $net];
    }
}
