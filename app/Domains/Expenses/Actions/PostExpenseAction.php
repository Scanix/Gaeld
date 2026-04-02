<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Enums\ExpenseType;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use Illuminate\Support\Facades\Log;

/**
 * Posts an approved expense to the ledger and records the payment journal entry.
 */
class PostExpenseAction
{
    private const REFERENCE_PREFIX_EXPENSE = 'EXP-';

    private const REFERENCE_PREFIX_CREDIT_NOTE = 'EXPCN-';

    public function __construct(
        private ExpenseService $expenseService,
    ) {}

    /**
     * Post an expense to the ledger.
     *
     * For regular invoices:
     *   Debit  {expenseAccountCode} Expense account  (expense amount)
     *   Credit {bankAccountCode}    Bank / Cash       (expense amount)
     *
     * For credit notes (reversed):
     *   Debit  {bankAccountCode}    Bank / Cash       (credit amount)
     *   Credit {expenseAccountCode} Expense account   (credit amount)
     *
     * @throws InvalidExpenseStateException When expense is already posted
     */
    public function execute(Expense $expense, string $expenseAccountCode, string $bankAccountCode = AccountCode::BANK_CASH): Expense
    {
        if (! $expense->status->canTransitionTo(ExpenseStatus::Posted)) {
            throw new InvalidExpenseStateException('Expense is already posted.');
        }

        $isCreditNote = ($expense->type ?? ExpenseType::Invoice) === ExpenseType::CreditNote;
        $prefix = $isCreditNote ? self::REFERENCE_PREFIX_CREDIT_NOTE : self::REFERENCE_PREFIX_EXPENSE;

        $reference = $prefix.$expense->id;

        $this->expenseService->postToLedger($expense, new RecordExpensePaymentData(
            amount: (string) $expense->amount,
            paymentDate: $expense->date->toDateString(),
            reference: $reference,
            description: $expense->description ?? $expense->category,
            expenseAccountCode: $expenseAccountCode,
            bankAccountCode: $bankAccountCode,
        ), $isCreditNote);

        Log::info('Expense posted to ledger', [
            'expense_id' => $expense->id,
            'reference' => $reference,
            'amount' => (string) $expense->amount,
            'is_credit_note' => $isCreditNote,
            'organization_id' => $expense->organization_id,
        ]);

        return $expense->fresh(['journalEntry.lines']);
    }
}
