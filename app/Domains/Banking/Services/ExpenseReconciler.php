<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles bank transactions against expenses.
 */
class ExpenseReconciler
{
    use ReconciliationPreconditions;
    use ReconciliationReference;

    private const REFERENCE_PREFIX = 'REC-';

    public function __construct(
        private LedgerService $ledgerService,
        private ExpenseService $expenseService,
        private BankingService $bankingService,
    ) {}

    /**
     * Manually reconcile a bank transaction with an expense.
     *
     * @throws AlreadyReconciledException
     * @throws UnlinkedBankAccountException
     */
    public function reconcileWithExpense(
        BankTransaction $transaction,
        Expense $expense,
        string $expenseAccountCode,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $expense, $expenseAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            $this->validatePreconditions($transaction, $bankAccount);

            $amount = Money::absoluteAmount((string) $transaction->amount);
            $reference = $this->buildReference($orgId, $transaction);

            $journalEntry = $this->expenseService->postToLedger($expense, RecordExpensePaymentData::forReconciliation(
                amount: $amount,
                paymentDate: $transaction->date->toDateString(),
                reference: $reference,
                transactionDescription: $transaction->description,
                expenseDescription: $expense->description,
                expenseAccountCode: $expenseAccountCode,
                bankAccountCode: $bankAccount->ledgerAccount->code,
            ));

            $this->bankingService->updateBankAccountBalance($bankAccount, $amount, false);

            $transaction->update([
                'journal_entry_id' => $journalEntry->id,
                'matched_expense_id' => $expense->id,
                'is_reconciled' => true,
            ]);

            return $transaction->fresh(['journalEntry.lines', 'matchedExpense', 'bankAccount']);
        });
    }
}
