<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\ReconciliationFailedException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\Exceptions\FeatureDisabledException;
use App\Support\FeatureFlag;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Façade that delegates bank reconciliation to specialized reconcilers.
 *
 * Coordinates InvoiceReconciler, ExpenseReconciler, and ContraAccountReconciler,
 * plus the auto-reconciliation engine (EE only).
 */
class ReconciliationService
{
    public function __construct(
        private InvoiceReconciler $invoiceReconciler,
        private ExpenseReconciler $expenseReconciler,
        private ContraAccountReconciler $contraAccountReconciler,
        private SuggestionService $suggestionService,
    ) {}

    public function reconcileWithInvoice(
        BankTransaction $transaction,
        Invoice $invoice,
        string $bankAccountCode = AccountCode::BANK_CASH,
    ): BankTransaction {
        return $this->invoiceReconciler->reconcileWithInvoice($transaction, $invoice, $bankAccountCode);
    }

    public function reconcileWithExpense(
        BankTransaction $transaction,
        Expense $expense,
        string $expenseAccountCode = AccountCode::GENERAL_EXPENSE,
    ): BankTransaction {
        return $this->expenseReconciler->reconcileWithExpense($transaction, $expense, $expenseAccountCode);
    }

    public function reconcileWithContraAccount(
        BankTransaction $transaction,
        string $contraAccountCode,
    ): BankTransaction {
        return $this->contraAccountReconciler->reconcileWithContraAccount($transaction, $contraAccountCode);
    }

    public function reconcileAsPersonal(BankTransaction $transaction): BankTransaction
    {
        return $this->contraAccountReconciler->reconcileAsPersonal($transaction);
    }

    public function reconcileWithVatSettlement(BankTransaction $transaction, JournalEntry $settlement): BankTransaction
    {
        $transaction->loadMissing('bankAccount');
        $settlement->loadMissing('lines.account');

        $bankAccount = $transaction->getRelation('bankAccount');
        if (! $bankAccount instanceof BankAccount || $bankAccount->organization_id !== $settlement->organization_id) {
            throw new ReconciliationFailedException(__('app.vat_payment_does_not_match_balance'));
        }

        if (! $settlement->is_posted || $settlement->type !== 'vat_settlement') {
            throw new ReconciliationFailedException(__('app.vat_settlement_not_available'));
        }

        if (BankTransaction::query()
            ->whereHas('bankAccount', fn ($query) => $query->where('organization_id', $settlement->organization_id))
            ->where('vat_settlement_journal_entry_id', $settlement->id)
            ->exists()) {
            throw new ReconciliationFailedException(__('app.vat_settlement_already_reconciled'));
        }

        $vatLine = $settlement->lines->first(
            fn ($line): bool => $line->account?->code === AccountCode::VAT_PAYABLE_AFC,
        );

        if (! $vatLine) {
            throw new ReconciliationFailedException(__('app.vat_settlement_not_available'));
        }

        $expectedAmount = Money::isPositive((string) $vatLine->credit)
            ? (string) $vatLine->credit
            : (string) $vatLine->debit;
        $amount = Money::absoluteAmount((string) $transaction->amount);

        $isPayable = Money::isPositive((string) $vatLine->credit)
            && $transaction->type === BankTransactionType::Debit;
        $isRefund = Money::isPositive((string) $vatLine->debit)
            && $transaction->type === BankTransactionType::Credit;

        if ((! $isPayable && ! $isRefund) || Money::compare($amount, $expectedAmount) !== 0) {
            throw new ReconciliationFailedException(__('app.vat_payment_does_not_match_balance'));
        }

        $reconciled = $this->contraAccountReconciler->reconcileWithContraAccount(
            $transaction,
            AccountCode::VAT_PAYABLE_AFC,
        );

        $reconciled->update(['vat_settlement_journal_entry_id' => $settlement->id]);

        return $reconciled->fresh(['journalEntry.lines', 'bankAccount', 'vatSettlementJournalEntry']);
    }

    /**
     * @param  Collection<int, BankTransaction>  $transactions
     * @return array<string, mixed>
     */
    public function bulkReconcileAsPersonal(Collection $transactions): array
    {
        return $this->contraAccountReconciler->bulkReconcileAsPersonal($transactions);
    }

    public function confirmMatch(BankMatch $match): BankTransaction
    {
        return $this->invoiceReconciler->confirmMatch($match);
    }

    // ──────────────────────────────────────────────────────────────
    //  EE: Auto Reconciliation (feature-flagged)
    // ──────────────────────────────────────────────────────────────

    /**
     * Automatically reconcile all unreconciled transactions for a bank account.
     *
     * @return array{matched: int, unmatched: int}
     *
     * @throws FeatureDisabledException
     */
    public function autoReconcile(BankAccount $bankAccount): array
    {
        if (FeatureFlag::disabled('auto_reconciliation')) {
            throw new FeatureDisabledException('auto_reconciliation');
        }

        $unreconciled = $bankAccount->transactions()
            ->where('is_reconciled', false)
            ->get();

        $matched = 0;
        $unmatched = 0;

        foreach ($unreconciled as $transaction) {
            $suggestions = $this->suggestionService->generateSuggestions($transaction);

            $reconciled = $this->tryAutoReconcileTransaction($transaction, $suggestions);

            $reconciled ? $matched++ : $unmatched++;
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * @param  array<string, mixed>  $suggestions
     */
    private function tryAutoReconcileTransaction(BankTransaction $transaction, array $suggestions): bool
    {
        $exactMatch = $suggestions['matches']->first(fn ($m) => $m->confidence === 100);

        if ($exactMatch) {
            try {
                $this->invoiceReconciler->confirmMatch($exactMatch);

                return true;
            } catch (AlreadyReconciledException|UnlinkedBankAccountException|ReconciliationFailedException $e) {
                Log::warning('Auto-reconcile: skipped match', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bestExpense = $suggestions['expenses']->first();

        if ($bestExpense && $bestExpense->score >= MatchConfidence::AutoExpenseThreshold->value) {
            $expenseAccountCode = $bestExpense->expense->expense_account_code;

            if (! $expenseAccountCode) {
                Log::warning('Auto-reconcile: skipped expense match (missing expense_account_code)', [
                    'transaction_id' => $transaction->id,
                    'expense_id' => $bestExpense->expense->id,
                ]);

                return false;
            }

            try {
                $this->expenseReconciler->reconcileWithExpense($transaction, $bestExpense->expense, $expenseAccountCode);

                return true;
            } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
                Log::warning('Auto-reconcile: skipped expense match', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }
}
