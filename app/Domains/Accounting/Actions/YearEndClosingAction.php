<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Models\Organization;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performs fiscal year-end closing: transfers P&L balances to equity
 * and archives documents for legal retention.
 */
class YearEndClosingAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LegalArchivingService $archiving,
        private readonly GenerateOpeningBalancesAction $openingBalances,
    ) {}

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
                if (Money::isZero((string) $row['balance'])) {
                    continue;
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $row['account_id'],
                    debit: (string) $row['balance'],
                    credit: '0',
                    description: "Bouclement {$year} — clôture ".$row['code'],
                );
                $netCreditOnResult = Money::add($netCreditOnResult, (string) $row['balance']);
            }

            // Expense accounts (debit-normal): credit the account, debit result
            foreach ($expenses as $row) {
                if (Money::isZero((string) $row['balance'])) {
                    continue;
                }
                $lines[] = new JournalLineData(
                    accountId: (string) $row['account_id'],
                    debit: '0',
                    credit: (string) $row['balance'],
                    description: "Bouclement {$year} — clôture ".$row['code'],
                );
                $netDebitOnResult = Money::add($netDebitOnResult, (string) $row['balance']);
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

            Log::info('Year-end closing journal entry posted', [
                'organization_id' => $orgId,
                'fiscal_year' => $year,
                'closing_date' => $closingDate,
                'line_count' => count($lines),
            ]);
        });

        // Archive all documents for the closed fiscal year (Swiss OR Art. 958f CO)
        $this->archiving->archiveFiscalYear($orgId, $year);

        // Lock the fiscal year to prevent further postings
        Organization::findOrFail($orgId)->closeFiscalYear($year);

        // Generate opening balance entries for the next fiscal year
        $this->openingBalances->execute($orgId, $year);

        Log::info('Year-end closing completed', [
            'organization_id' => $orgId,
            'fiscal_year' => $year,
            'closing_date' => $closingDate,
            'reference' => $reference,
            'revenue_accounts_closed' => count($income),
            'expense_accounts_closed' => count($expenses),
        ]);
    }
}
