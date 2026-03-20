<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Services\LedgerService;
use App\Support\Exceptions\FeatureDisabledException;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Support\FeatureFlag;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconciliationService
{
    private const REFERENCE_PREFIX_RECONCILIATION = 'REC-';

    public function __construct(
        private LedgerService $ledgerService,
        private InvoiceService $invoiceService,
        private ExpenseService $expenseService,
        private BankingService $bankingService,
        private MatchingEngine $matchingEngine,
        private SuggestionService $suggestionService,
    ) {}

    /**
     * Validate preconditions common to all reconciliation paths.
     *
     * @throws AlreadyReconciledException
     * @throws UnlinkedBankAccountException
     */
    private function validateReconciliationPreconditions(BankTransaction $transaction, BankAccount $bankAccount): void
    {
        if (! $bankAccount->ledgerAccount) {
            throw new UnlinkedBankAccountException();
        }

        if ($transaction->is_reconciled) {
            throw new AlreadyReconciledException();
        }
    }

    /**
     * Build a unique reconciliation reference, appending a UUID suffix if the base reference already exists.
     */
    private function buildReconciliationReference(string $orgId, BankTransaction $transaction): string
    {
        $reference = self::REFERENCE_PREFIX_RECONCILIATION . ($transaction->reference ?? $transaction->id);

        if ($this->ledgerService->isDuplicateReference($orgId, $reference)) {
            $reference .= '-' . Str::uuid()->toString();
        }

        return $reference;
    }

    /**
     * Resolve the payment amount: clamped to invoice's outstanding balance.
     */
    private function resolvePaymentAmount(BankTransaction $transaction, Invoice $invoice): string
    {
        $amount = Money::absoluteAmount((string) $transaction->amount);
        $amountDue = $invoice->amountDue();

        return bccomp($amount, $amountDue, 2) <= 0 ? $amount : $amountDue;
    }

    // ──────────────────────────────────────────────────────────────
    //  CE: Manual Reconciliation
    // ──────────────────────────────────────────────────────────────

    /**
     * Record a payment for an invoice and mark the transaction as reconciled.
     *
     * Shared by reconcileWithInvoice() and confirmMatch() to avoid duplicating
     * the validate→pay→update sequence.
     */
    private function recordInvoicePaymentAndReconcile(
        BankTransaction $transaction,
        Invoice $invoice,
        BankAccount $bankAccount,
        ?string $bankAccountCode = null,
    ): void {
        $paymentAmount = $this->resolvePaymentAmount($transaction, $invoice);
        $orgId = $bankAccount->organization_id;

        $payment = null;
        if (bccomp($paymentAmount, '0', 2) > 0) {
            $payment = $this->invoiceService->recordPayment($invoice, new RecordPaymentData(
                amount: $paymentAmount,
                paymentDate: $transaction->date->toDateString(),
                paymentMethod: PaymentMethod::Bank,
                reference: $this->buildReconciliationReference($orgId, $transaction),
                bankAccountCode: $bankAccountCode,
            ));
        }

        $this->bankingService->updateBankAccountBalance($bankAccount, $paymentAmount, true);

        $transaction->update([
            'journal_entry_id' => $payment?->journal_entry_id,
            'matched_invoice_id' => $invoice->id,
            'is_reconciled' => true,
        ]);
    }

    /**
     * Manually reconcile a bank transaction with an invoice.
     *
     * Delegates to InvoiceService::recordPayment() which posts the journal entry
     * (debit bank, credit AR), creates an InvoicePayment record, and marks the
     * invoice as Paid when fully settled.
     *
     * @throws AlreadyReconciledException  When transaction is already reconciled
     * @throws UnlinkedBankAccountException  When bank account is not linked to a ledger account
     */
    public function reconcileWithInvoice(
        BankTransaction $transaction,
        Invoice $invoice,
        string $bankAccountCode = AccountCode::BANK_CASH,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $invoice, $bankAccountCode) {
            $bankAccount = $transaction->bankAccount;

            $this->validateReconciliationPreconditions($transaction, $bankAccount);

            $this->recordInvoicePaymentAndReconcile($transaction, $invoice, $bankAccount, $bankAccountCode);

            return $transaction->fresh(['journalEntry.lines', 'matchedInvoice', 'bankAccount']);
        });
    }


    /**
     * Manually reconcile a bank transaction with an expense.
     *
     * Posts the bank transaction to the ledger (debit expense account, credit bank)
     * and marks the transaction as reconciled.
     *
     * @throws AlreadyReconciledException  When transaction is already reconciled
     * @throws UnlinkedBankAccountException  When bank account is not linked to a ledger account
     */
    public function reconcileWithExpense(
        BankTransaction $transaction,
        Expense $expense,
        string $expenseAccountCode = AccountCode::GENERAL_EXPENSE,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $expense, $expenseAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            $this->validateReconciliationPreconditions($transaction, $bankAccount);

            $amount = Money::absoluteAmount((string) $transaction->amount);
            $reference = $this->buildReconciliationReference($orgId, $transaction);

            $journalEntry = $this->expenseService->recordBankPayment($expense, RecordExpensePaymentData::forReconciliation(
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

    /**
     * Manually reconcile a bank transaction with a contra account (no invoice/expense match).
     *
     * @throws AlreadyReconciledException  When transaction is already reconciled
     * @throws UnlinkedBankAccountException  When bank account has no linked ledger account
     */
    public function reconcileWithContraAccount(
        BankTransaction $transaction,
        string $contraAccountCode,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $contraAccountCode) {
            $bankAccount = $transaction->bankAccount;

            $this->validateReconciliationPreconditions($transaction, $bankAccount);

            $postedTransaction = $this->bankingService->postBankTransaction($transaction, $contraAccountCode);

            $postedTransaction->update(['is_reconciled' => true]);

            return $postedTransaction->fresh(['journalEntry.lines', 'bankAccount']);
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  CE: Match Confirmation
    // ──────────────────────────────────────────────────────────────

    /**
     * Confirm a match: record the payment via the standard pipeline
     * and mark the bank transaction as reconciled.
     *
     * Uses recordPayment() alone — which already posts the journal entry
     * (debit bank, credit AR). Does NOT also call reconcileWithInvoice()
     * to avoid double-posting the same accounting entry.
     *
     * @throws AlreadyReconciledException  When transaction is already reconciled
     * @throws InvalidPaymentException  When duplicate payment detected
     * @throws UnlinkedBankAccountException  When bank account is not linked to a ledger account
     */
    public function confirmMatch(BankMatch $match): BankTransaction
    {
        $transaction = $match->bankTransaction;
        $invoice = $match->invoice;
        $bankAccount = $transaction->bankAccount;

        return DB::transaction(function () use ($match, $transaction, $invoice, $bankAccount) {
            if ($this->isDuplicatePayment($transaction, $invoice)) {
                throw new InvalidPaymentException('This payment has already been recorded for this invoice.');
            }

            $this->validateReconciliationPreconditions($transaction, $bankAccount);

            $this->recordInvoicePaymentAndReconcile($transaction, $invoice, $bankAccount);

            $match->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            return $transaction->fresh(['journalEntry.lines', 'matchedInvoice', 'bankAccount']);
        });
    }

    /**
     * Check if a payment has already been recorded for this transaction-invoice pair.
     */
    private function isDuplicatePayment(BankTransaction $transaction, Invoice $invoice): bool
    {
        if ($transaction->matched_invoice_id === $invoice->id) {
            return true;
        }

        return BankMatch::where('bank_transaction_id', $transaction->id)
            ->where('invoice_id', $invoice->id)
            ->where('is_confirmed', true)
            ->exists();
    }

    // ──────────────────────────────────────────────────────────────
    //  EE: Auto Reconciliation (feature-flagged)
    // ──────────────────────────────────────────────────────────────

    /**
     * Automatically reconcile all unreconciled transactions for a bank account.
     *
     * Uses the smart matching engine:
     *   - Confidence 100 (exact QR match): auto-reconcile + record payment
     *   - Confidence 90/70: store matches for manual review
     *
     * EE only — guarded by 'auto_reconciliation' feature flag.
     *
     * @return array{matched: int, unmatched: int}
     *
     * @throws FeatureDisabledException  When auto reconciliation feature is disabled
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
     * Attempt to auto-reconcile a single transaction. Returns true if reconciled.
     */
    private function tryAutoReconcileTransaction(BankTransaction $transaction, array $suggestions): bool
    {
        // Only auto-confirm exact QR reference matches (confidence = 100)
        $exactMatch = $suggestions['matches']->first(fn ($m) => $m->confidence === 100);

        if ($exactMatch) {
            try {
                $this->confirmMatch($exactMatch);

                return true;
            } catch (AlreadyReconciledException|UnlinkedBankAccountException|InvalidPaymentException $e) {
                Log::warning('Auto-reconcile: skipped match', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Auto-reconcile expenses with high confidence
        $bestExpense = $suggestions['expenses']->first();

        if ($bestExpense && $bestExpense->score >= MatchConfidence::AutoExpenseThreshold->value) {
            try {
                $this->reconcileWithExpense($transaction, $bestExpense->expense);

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
