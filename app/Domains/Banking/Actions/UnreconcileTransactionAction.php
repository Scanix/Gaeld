<?php

namespace App\Domains\Banking\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Exceptions\NotReconciledException;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a bank reconciliation match, whichever type it was (invoice
 * payment, expense payment, or a manual contra-account/personal booking).
 * Reverses the transaction's journal entry, undoes the invoice/expense
 * side-effects that reconciling created, reverts the bank account balance,
 * and resets the transaction + any bank match to their pre-reconciliation
 * state so the user can correct a wrong match instead of being stuck.
 */
class UnreconcileTransactionAction
{
    public function __construct(
        private LedgerService $ledgerService,
        private BankingService $bankingService,
    ) {}

    public function execute(BankTransaction $transaction): BankTransaction
    {
        if (! $transaction->is_reconciled) {
            throw new NotReconciledException;
        }

        return DB::transaction(function () use ($transaction) {
            $bankAccount = $transaction->bankAccount;
            $amount = Money::absoluteAmount((string) $transaction->amount);
            $wasDeposit = $transaction->type === BankTransactionType::Credit;

            if ($transaction->journal_entry_id) {
                $transaction->loadMissing('journalEntry.lines');
                $reversal = $this->ledgerService->reverseEntry(
                    $transaction->journalEntry,
                    "Unreconciling bank transaction #{$transaction->id}",
                );
                $this->ledgerService->postDraft($reversal);
            }

            if ($transaction->matched_invoice_id) {
                $this->revertInvoicePayment($transaction);
            }

            if ($transaction->matched_expense_id) {
                $this->revertExpensePosting($transaction);
            }

            if ($transaction->journal_entry_id) {
                $this->bankingService->updateBankAccountBalance($bankAccount, $amount, ! $wasDeposit);
            }

            BankMatch::where('bank_transaction_id', $transaction->id)
                ->update(['is_confirmed' => false, 'confirmed_at' => null]);

            $transaction->update([
                'journal_entry_id' => null,
                'vat_settlement_journal_entry_id' => null,
                'matched_invoice_id' => null,
                'matched_expense_id' => null,
                'is_reconciled' => false,
                'is_personal' => false,
            ]);

            return $transaction->fresh(['bankAccount']);
        });
    }

    private function revertInvoicePayment(BankTransaction $transaction): void
    {
        $payment = InvoicePayment::where('journal_entry_id', $transaction->journal_entry_id)->first();

        if (! $payment) {
            return;
        }

        $invoice = Invoice::find($transaction->matched_invoice_id);
        $payment->delete();

        if (! $invoice) {
            return;
        }

        if ($invoice->status === InvoiceStatus::Paid && ! $invoice->fresh()->isFullyPaid()) {
            $isOverdue = $invoice->due_date->isBefore(now()->startOfDay());
            $invoice->update(['status' => $isOverdue ? InvoiceStatus::Overdue : InvoiceStatus::Sent]);
        }
    }

    private function revertExpensePosting(BankTransaction $transaction): void
    {
        $expense = Expense::find($transaction->matched_expense_id);

        if (! $expense || $expense->journal_entry_id !== $transaction->journal_entry_id) {
            return;
        }

        $expense->update([
            'status' => ExpenseStatus::Approved,
            'journal_entry_id' => null,
        ]);
    }
}
