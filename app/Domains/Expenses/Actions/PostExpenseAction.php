<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Support\Facades\DB;

class PostExpenseAction
{
    private const REFERENCE_PREFIX_EXPENSE = 'EXP-';

    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Post an expense to the ledger.
     *
     * Accounting effect:
     *   Debit  {expenseAccountCode} Expense account  (expense amount)
     *   Credit {bankAccountCode}    Bank / Cash       (expense amount)
     *
     * @throws InvalidExpenseStateException  When expense is already posted
     */
    public function execute(Expense $expense, string $expenseAccountCode, string $bankAccountCode = AccountCode::BANK_CASH): Expense
    {
        if ($expense->status === ExpenseStatus::Posted) {
            throw new InvalidExpenseStateException('Expense is already posted.');
        }

        return DB::transaction(function () use ($expense, $expenseAccountCode, $bankAccountCode) {
            $orgId = $expense->organization_id;

            $expenseAccount = $this->ledgerService->resolveAccount($orgId, $expenseAccountCode);
            $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);

            $journalEntry = $this->ledgerService->postEntry($orgId, [
                'date' => $expense->date->toDateString(),
                'reference' => self::REFERENCE_PREFIX_EXPENSE . $expense->id,
                'description' => $expense->description ?? $expense->category,
            ], [
                ['account_id' => $expenseAccount->id, 'debit' => $expense->amount, 'credit' => 0, 'description' => $expense->description],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $expense->amount, 'description' => 'Payment from bank'],
            ]);

            $expense->update([
                'status' => ExpenseStatus::Posted->value,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $expense->fresh(['journalEntry.lines']);
        });
    }
}
