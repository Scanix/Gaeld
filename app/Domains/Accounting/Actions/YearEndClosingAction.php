<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use Illuminate\Support\Facades\DB;

class YearEndClosingAction
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Post the year-end closing journal entry.
     *
     * @param  array<array<string, mixed>>  $income  Revenue accounts with 'balance', 'account_id', 'code'
     * @param  array<array<string, mixed>>  $expenses  Expense accounts with 'balance', 'account_id', 'code'
     */
    public function execute(
        string $orgId,
        int $year,
        array $income,
        array $expenses,
        Account $resultAccount,
        string $closingDate,
        string $reference,
    ): void {
        DB::transaction(function () use ($income, $expenses, $year, $closingDate, $reference, $resultAccount, $orgId): void {
            $lines = [];
            $netDebitOnResult = '0';
            $netCreditOnResult = '0';

            // Revenue accounts (credit-normal): debit the account, credit result
            foreach ($income as $row) {
                if (bccomp((string) $row['balance'], '0', 2) === 0) {
                    continue;
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $row['account_id'],
                    debit: (string) $row['balance'],
                    credit: '0',
                    description: "Bouclement {$year} — clôture ".$row['code'],
                );
                $netCreditOnResult = bcadd($netCreditOnResult, (string) $row['balance'], 2);
            }

            // Expense accounts (debit-normal): credit the account, debit result
            foreach ($expenses as $row) {
                if (bccomp((string) $row['balance'], '0', 2) === 0) {
                    continue;
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $row['account_id'],
                    debit: '0',
                    credit: (string) $row['balance'],
                    description: "Bouclement {$year} — clôture ".$row['code'],
                );
                $netDebitOnResult = bcadd($netDebitOnResult, (string) $row['balance'], 2);
            }

            $lines[] = new JournalLineData(
                accountId: (string) $resultAccount->id,
                debit: $netDebitOnResult,
                credit: $netCreditOnResult,
                description: "Bouclement {$year} — résultat de l'exercice",
            );

            $entry = new JournalEntryData(
                date: $closingDate,
                reference: $reference,
                description: "Bouclement de compte {$year}",
                lines: $lines,
            );

            $this->ledger->postEntry($orgId, $entry);
        });
    }
}
