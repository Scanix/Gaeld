<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerService;
use App\Exceptions\FeatureDisabledException;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
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

    // ──────────────────────────────────────────────────────────────
    //  CE: Manual Reconciliation
    // ──────────────────────────────────────────────────────────────

    /**
     * Manually reconcile a bank transaction with an invoice.
     *
     * Posts the bank transaction to the ledger (debit bank, credit AR)
     * and marks the transaction as reconciled.
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
            $orgId = $bankAccount->organization_id;

            $this->validateReconciliationPreconditions($transaction, $bankAccount);

            $arAccount = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);
            $amount = Money::absoluteAmount((string) $transaction->amount);
            $reference = $this->buildReconciliationReference($orgId, $transaction);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $transaction->date->toDateString(),
                reference: $reference,
                description: "Reconciliation: {$transaction->description} ↔ Invoice {$invoice->number}",
                lines: [
                    new JournalLineData(accountId: $bankAccount->ledgerAccount->id, debit: $amount, credit: 0, description: 'Bank deposit'),
                    new JournalLineData(accountId: $arAccount->id, debit: 0, credit: $amount, description: "Payment for invoice {$invoice->number}"),
                ],
            ));

            $this->bankingService->updateBankAccountBalance($bankAccount, (string) $amount, true);

            $transaction->update([
                'journal_entry_id' => $journalEntry->id,
                'matched_invoice_id' => $invoice->id,
                'is_reconciled' => true,
            ]);

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

            $expenseAccount = $this->ledgerService->resolveAccount($orgId, $expenseAccountCode);
            $amount = Money::absoluteAmount((string) $transaction->amount);
            $reference = $this->buildReconciliationReference($orgId, $transaction);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $transaction->date->toDateString(),
                reference: $reference,
                description: "Reconciliation: {$transaction->description} ↔ Expense {$expense->description}",
                lines: [
                    new JournalLineData(accountId: $expenseAccount->id, debit: $amount, credit: 0, description: $expense->description ?? 'Expense'),
                    new JournalLineData(accountId: $bankAccount->ledgerAccount->id, debit: 0, credit: $amount, description: 'Bank withdrawal'),
                ],
            ));

            $this->bankingService->updateBankAccountBalance($bankAccount, (string) $amount, false);

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
    public function reconcileManual(
        BankTransaction $transaction,
        string $contraAccountCode,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $contraAccountCode) {
            $bankAccount = $transaction->bankAccount;

            $this->validateReconciliationPreconditions($transaction, $bankAccount);

            $result = $this->bankingService->postBankTransaction($transaction, $contraAccountCode);

            $result->update(['is_reconciled' => true]);

            return $result->fresh(['journalEntry.lines', 'bankAccount']);
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

        if ($this->isDuplicatePayment($transaction, $invoice)) {
            throw new \App\Domains\Invoicing\Exceptions\InvalidPaymentException('This payment has already been recorded for this invoice.');
        }

        $bankAccount = $transaction->bankAccount;
        $this->validateReconciliationPreconditions($transaction, $bankAccount);

        return DB::transaction(function () use ($match, $transaction, $invoice, $bankAccount) {
            $amount = Money::absoluteAmount((string) $transaction->amount);
            $amountDue = $invoice->amountDue();
            $paymentAmount = bccomp($amount, $amountDue, 2) <= 0 ? $amount : $amountDue;

            $payment = null;
            if (bccomp($paymentAmount, '0', 2) > 0) {
                $payment = $this->invoiceService->recordPayment($invoice, new RecordPaymentData(
                    amount: $paymentAmount,
                    paymentDate: $transaction->date->toDateString(),
                    paymentMethod: 'bank',
                    reference: self::REFERENCE_PREFIX_RECONCILIATION . ($transaction->reference ?? $transaction->id),
                ));
            }

            $this->bankingService->updateBankAccountBalance($bankAccount, $paymentAmount ?? $amount, true);

            $transaction->update([
                'journal_entry_id' => $payment?->journal_entry_id,
                'matched_invoice_id' => $invoice->id,
                'is_reconciled' => true,
            ]);

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
    //  CE: Suggestions (delegates to SuggestionService)
    // ──────────────────────────────────────────────────────────────

    /**
     * Get reconciliation suggestions for a paginated collection of transactions.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array<int, array{invoices: Collection, expenses: Collection, matches: Collection}>
     */
    public function generateSuggestionsForTransactions(iterable $transactions): array
    {
        return $this->suggestionService->generateSuggestionsForTransactions($transactions);
    }

    /**
     * Get reconciliation suggestions for a single bank transaction.
     *
     * @return array{invoices: Collection, expenses: Collection, matches: Collection}
     */
    public function generateSuggestions(BankTransaction $transaction): array
    {
        return $this->suggestionService->generateSuggestions($transaction);
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

            // Only auto-confirm exact QR reference matches (confidence = 100)
            $exactMatch = $suggestions['matches']->first(fn ($m) => $m->confidence === 100);

            if ($exactMatch) {
                try {
                    $this->confirmMatch($exactMatch);
                    $matched++;

                    continue;
                } catch (AlreadyReconciledException|UnlinkedBankAccountException|\App\Domains\Invoicing\Exceptions\InvalidPaymentException $e) {
                    Log::warning('Auto-reconcile: skipped match', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Auto-reconcile expenses with high confidence
            $bestExpense = $suggestions['expenses']->first();

            if ($bestExpense && $bestExpense->match_score >= MatchConfidence::AutoExpenseThreshold->value) {
                try {
                    $this->reconcileWithExpense($transaction, $bestExpense);
                    $matched++;

                    continue;
                } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
                    Log::warning('Auto-reconcile: skipped expense match', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $unmatched++;
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }
}
