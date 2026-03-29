<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;

/**
 * Posts an approved expense to the ledger and records the payment journal entry.
 */
class PostExpenseAction
{
    private const REFERENCE_PREFIX_EXPENSE = 'EXP-';

    public function __construct(
        private ExpenseService $expenseService,
    ) {}

    /**
     * Post an expense to the ledger.
     *
     * Accounting effect:
     *   Debit  {expenseAccountCode} Expense account  (expense amount)
     *   Credit {bankAccountCode}    Bank / Cash       (expense amount)
     *
     * @throws InvalidExpenseStateException When expense is already posted
     */
    public function execute(Expense $expense, string $expenseAccountCode, string $bankAccountCode = AccountCode::BANK_CASH): Expense
    {
        if (! $expense->status->canTransitionTo(ExpenseStatus::Posted)) {
            throw new InvalidExpenseStateException('Expense is already posted.');
        }

        $this->expenseService->postToLedger($expense, new RecordExpensePaymentData(
            amount: (string) $expense->amount,
            paymentDate: $expense->date->toDateString(),
            reference: self::REFERENCE_PREFIX_EXPENSE.$expense->id,
            description: $expense->description ?? $expense->category,
            expenseAccountCode: $expenseAccountCode,
            bankAccountCode: $bankAccountCode,
        ));

        return $expense->fresh(['journalEntry.lines']);
    }
}
