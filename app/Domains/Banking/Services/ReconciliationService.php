<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\AccountCode;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Exceptions\FeatureDisabledException;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\MatchConfidence;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Invoicing\Models\Invoice;
use App\Services\FeatureFlag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconciliationService
{
    public function __construct(
        private LedgerService $ledgerService,
        private InvoiceService $invoiceService,
    ) {}

    private function absoluteAmount(string $value): string
    {
        return bccomp($value, '0', 2) < 0 ? bcmul($value, '-1', 2) : $value;
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
     * @throws \DomainException  When bank account is not linked to a ledger account
     */
    public function reconcileWithInvoice(
        BankTransaction $transaction,
        Invoice $invoice,
        string $bankAccountCode = AccountCode::BANK_CASH,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $invoice, $bankAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            $bankLedgerAccount = $bankAccount->ledgerAccount;
            if (! $bankLedgerAccount) {
                throw new UnlinkedBankAccountException();
            }

            $arAccount = $this->ledgerService->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);
            $amount = $this->absoluteAmount((string) $transaction->amount);

            $reference = 'REC-' . ($transaction->reference ?? $transaction->id);

            // Skip if already reconciled
            if ($transaction->is_reconciled) {
                throw new AlreadyReconciledException();
            }

            // Guard against duplicate reconciliation
            if ($this->ledgerService->isDuplicateReference($orgId, $reference)) {
                $reference = $reference . '-' . Str::uuid()->toString();
            }

            $journalEntry = $this->ledgerService->postEntry($orgId, [
                'date' => $transaction->date->toDateString(),
                'reference' => $reference,
                'description' => "Reconciliation: {$transaction->description} ↔ Invoice {$invoice->number}",
            ], [
                ['account_id' => $bankLedgerAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Bank deposit'],
                ['account_id' => $arAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => "Payment for invoice {$invoice->number}"],
            ]);

            // Update bank account balance via LedgerService
            $this->ledgerService->updateBankAccountBalance($bankAccount, (string) $amount, true);

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
     * @throws \DomainException  When bank account is not linked to a ledger account
     */
    public function reconcileWithExpense(
        BankTransaction $transaction,
        Expense $expense,
        string $expenseAccountCode = AccountCode::GENERAL_EXPENSE,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $expense, $expenseAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            $bankLedgerAccount = $bankAccount->ledgerAccount;
            if (! $bankLedgerAccount) {
                throw new UnlinkedBankAccountException();
            }

            $expenseAccount = $this->ledgerService->resolveAccount($orgId, $expenseAccountCode);
            $amount = $this->absoluteAmount((string) $transaction->amount);

            $reference = 'REC-' . ($transaction->reference ?? $transaction->id);

            if ($transaction->is_reconciled) {
                throw new AlreadyReconciledException();
            }

            if ($this->ledgerService->isDuplicateReference($orgId, $reference)) {
                $reference = $reference . '-' . Str::uuid()->toString();
            }

            $journalEntry = $this->ledgerService->postEntry($orgId, [
                'date' => $transaction->date->toDateString(),
                'reference' => $reference,
                'description' => "Reconciliation: {$transaction->description} ↔ Expense {$expense->description}",
            ], [
                ['account_id' => $expenseAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => $expense->description ?? 'Expense'],
                ['account_id' => $bankLedgerAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Bank withdrawal'],
            ]);

            // Update bank account balance via LedgerService
            $this->ledgerService->updateBankAccountBalance($bankAccount, (string) $amount, false);

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
     * @throws \DomainException  When bank account has no linked ledger account
     */
    public function reconcileManual(
        BankTransaction $transaction,
        string $contraAccountCode,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $contraAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            if ($transaction->is_reconciled) {
                throw new AlreadyReconciledException();
            }

            $result = $this->ledgerService->postBankTransaction($transaction, $contraAccountCode);

            $result->update(['is_reconciled' => true]);

            return $result->fresh(['journalEntry.lines', 'bankAccount']);
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  CE: Smart Matching Engine
    // ──────────────────────────────────────────────────────────────

    /**
     * Find and store matches for a bank transaction.
     *
     * Priority order:
     *   1. QR reference match (confidence = 100)
     *   2. Amount + client name match (confidence = 90)
     *   3. Heuristic match (confidence = 70)
     *
     * Results are stored in the bank_matches table.
     *
     * @return Collection<BankMatch>
     */
    public function findMatches(BankTransaction $transaction): Collection
    {
        $orgId = $transaction->bankAccount->organization_id;
        $amount = $this->absoluteAmount((string) $transaction->amount);

        // Only match credit transactions to invoices
        if ($transaction->type !== BankTransaction::TYPE_CREDIT) {
            return collect();
        }

        $matches = collect();

        // Priority 1: Exact QR reference match
        $qrMatch = $this->matchByQrReference($orgId, $transaction);
        if ($qrMatch) {
            $matches->push($qrMatch);

            return $this->storeMatches($transaction, $matches);
        }

        // Priority 2: Amount + client name match
        $amountClientMatches = $this->matchByAmountAndClient($orgId, $transaction, $amount);
        $matches = $matches->merge($amountClientMatches);

        // Priority 3: Heuristic matching (amount or reference)
        $heuristicMatches = $this->matchByHeuristics($orgId, $transaction, $amount);
        // Filter out invoices already matched at higher confidence
        $existingInvoiceIds = $matches->pluck('invoice_id')->toArray();
        $heuristicMatches = $heuristicMatches->filter(fn ($m) => ! in_array($m['invoice_id'], $existingInvoiceIds));
        $matches = $matches->merge($heuristicMatches);

        return $this->storeMatches($transaction, $matches);
    }

    /**
     * Match by exact QR reference (structured_reference ↔ invoice.qr_reference).
     *
     * @return array{invoice_id: string, confidence: int, match_type: string}|null The match, or null if no QR reference match found
     */
    private function matchByQrReference(string $orgId, BankTransaction $transaction): ?array
    {
        $ref = $transaction->structured_reference;
        if (! $ref) {
            return null;
        }

        $invoice = Invoice::where('organization_id', $orgId)
            ->where('qr_reference', $ref)
            ->whereIn('status', ['sent', 'overdue'])
            ->first();

        if (! $invoice) {
            return null;
        }

        return [
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::QR_REFERENCE,
            'match_type' => BankMatch::TYPE_QR_REFERENCE,
        ];
    }

    /**
     * Match by exact amount AND client name.
     * Confidence: 90
     */
    private function matchByAmountAndClient(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        if (! $transaction->debtor_name) {
            return collect();
        }

        $invoices = Invoice::where('organization_id', $orgId)
            ->whereIn('status', ['sent', 'overdue'])
            ->whereBetween('total', [
                bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
            ])
            ->with(['customer', 'client'])
            ->get();

        return $invoices->filter(function ($invoice) use ($transaction) {
            $contact = $invoice->customer ?? $invoice->client;
            if (! $contact) {
                return false;
            }

            return str_contains(strtolower($transaction->debtor_name), strtolower($contact->name))
                || str_contains(strtolower($contact->name), strtolower($transaction->debtor_name));
        })->map(fn ($invoice) => [
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::AMOUNT_AND_CLIENT,
            'match_type' => BankMatch::TYPE_AMOUNT_CLIENT,
        ])->values();
    }

    /**
     * Match by amount OR reference (fallback).
     * Confidence: 70
     */
    private function matchByHeuristics(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        $query = Invoice::where('organization_id', $orgId)
            ->whereIn('status', ['sent', 'overdue'])
            ->where(function ($q) use ($amount, $transaction) {
                $q->whereBetween('total', [
                    bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                    bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                ]);

                if ($transaction->reference) {
                    $q->orWhere('number', 'like', '%' . $transaction->reference . '%');
                }
                if ($transaction->end_to_end_id) {
                    $q->orWhere('number', 'like', '%' . $transaction->end_to_end_id . '%');
                }
            })
            ->limit(5)
            ->get();

        return $query->map(fn ($invoice) => [
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::HEURISTIC,
            'match_type' => BankMatch::TYPE_HEURISTIC,
        ])->values();
    }

    /**
     * Persist match candidates to bank_matches table (replaces any existing).
     *
     * @return Collection<BankMatch>
     */
    private function storeMatches(BankTransaction $transaction, Collection $matches): Collection
    {
        // Clear previous unconfirmed matches for this transaction
        BankMatch::where('bank_transaction_id', $transaction->id)
            ->where('is_confirmed', false)
            ->delete();

        return $matches->map(fn ($match) => BankMatch::create([
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $match['invoice_id'],
            'confidence' => $match['confidence'],
            'match_type' => $match['match_type'],
        ]));
    }

    /**
     * Confirm a match: reconcile the transaction with the matched invoice
     * and record the payment via the standard payment pipeline.
     *
     * @throws AlreadyReconciledException  When transaction is already reconciled
     * @throws \DomainException  When duplicate payment detected
     */
    public function confirmMatch(BankMatch $match): BankTransaction
    {
        $transaction = $match->bankTransaction;
        $invoice = $match->invoice;

        if ($transaction->is_reconciled) {
            throw new AlreadyReconciledException();
        }

        // Prevent duplicate payment for the same invoice+transaction
        if ($this->isDuplicatePayment($transaction, $invoice)) {
            throw new \App\Domains\Invoicing\Exceptions\InvalidPaymentException('This payment has already been recorded for this invoice.');
        }

        return DB::transaction(function () use ($match, $transaction, $invoice) {
            // Record payment through the standard pipeline
            $amount = $this->absoluteAmount((string) $transaction->amount);
            $amountDue = $invoice->amountDue();
            $paymentAmount = bccomp($amount, $amountDue, 2) <= 0 ? $amount : $amountDue;

            if (bccomp($paymentAmount, '0', 2) > 0) {
                $this->invoiceService->recordPayment($invoice, [
                    'amount' => $paymentAmount,
                    'payment_date' => $transaction->date->toDateString(),
                    'payment_method' => 'bank',
                    'reference' => 'REC-' . ($transaction->reference ?? $transaction->id),
                ]);
            }

            // Reconcile the bank transaction
            $result = $this->reconcileWithInvoice($transaction, $invoice);

            // Mark match as confirmed
            $match->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            return $result;
        });
    }

    /**
     * Check if a payment has already been recorded for this transaction-invoice pair.
     */
    private function isDuplicatePayment(BankTransaction $transaction, Invoice $invoice): bool
    {
        // Check if transaction is already matched to this invoice
        if ($transaction->matched_invoice_id === $invoice->id) {
            return true;
        }

        // Check if there's already a confirmed match for this pair
        return BankMatch::where('bank_transaction_id', $transaction->id)
            ->where('invoice_id', $invoice->id)
            ->where('is_confirmed', true)
            ->exists();
    }

    // ──────────────────────────────────────────────────────────────
    //  CE: Basic Suggestions (backward compatible)
    // ──────────────────────────────────────────────────────────────

    /**
     * Get reconciliation suggestions for a paginated collection of transactions.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array<int, array{invoices: \Illuminate\Support\Collection, expenses: \Illuminate\Support\Collection, matches: \Illuminate\Support\Collection}>
     */
    public function getSuggestionsForTransactions(iterable $transactions): array
    {
        $suggestions = [];

        foreach ($transactions as $transaction) {
            if (! $transaction->is_reconciled) {
                $suggestions[$transaction->id] = $this->getSuggestions($transaction);
            }
        }

        return $suggestions;
    }

    /**
     * Get basic reconciliation suggestions for a bank transaction.
     *
     * Uses the new matching engine for invoices and legacy logic for expenses.
     *
     * @return array{invoices: Collection, expenses: Collection, matches: Collection}
     */
    public function getSuggestions(BankTransaction $transaction): array
    {
        $orgId = $transaction->bankAccount->organization_id;
        $amount = $this->absoluteAmount((string) $transaction->amount);

        // Use the matching engine for invoices
        $matches = $this->findMatches($transaction);

        // Load invoice data for the matches
        $invoiceSuggestions = $matches->map(function ($match) {
            $invoice = $match->invoice->load(['customer', 'client']);
            $invoice->match_score = $match->confidence;
            $invoice->match_type = $match->match_type;
            $invoice->match_id = $match->id;

            return $invoice;
        })->sortByDesc('match_score')->values();

        $expenseSuggestions = $this->suggestExpenses($orgId, $transaction, $amount);

        return [
            'invoices' => $invoiceSuggestions,
            'expenses' => $expenseSuggestions,
            'matches' => $matches,
        ];
    }

    private function suggestExpenses(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        if ($transaction->type !== BankTransaction::TYPE_DEBIT) {
            return collect();
        }

        $query = Expense::where('organization_id', $orgId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($amount, $transaction) {
                $q->whereBetween('amount', [
                    bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                    bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                ]);

                if ($transaction->creditor_name) {
                    $q->orWhere('vendor', 'like', '%' . $transaction->creditor_name . '%');
                }
            })
            ->limit(5);

        $results = $query->get();

        return $results->map(function ($expense) use ($amount, $transaction) {
            $score = 0;

            if (bccomp((string) $expense->amount, $amount, 2) === 0) {
                $score += 50;
            }

            if ($transaction->creditor_name && $expense->vendor) {
                if (str_contains(strtolower($transaction->creditor_name), strtolower($expense->vendor))
                    || str_contains(strtolower($expense->vendor), strtolower($transaction->creditor_name))) {
                    $score += 30;
                }
            }

            $expense->match_score = $score;

            return $expense;
        })->sortByDesc('match_score')->values();
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
            // Find and store matches
            $matches = $this->findMatches($transaction);

            // Only auto-confirm exact QR reference matches (confidence = 100)
            $exactMatch = $matches->first(fn ($m) => $m->confidence === 100);

            if ($exactMatch) {
                try {
                    $this->confirmMatch($exactMatch);
                    $matched++;

                    continue;
                } catch (\Exception $e) {
                    Log::warning('Auto-reconcile: skipped match', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Auto-reconcile expenses with high confidence
            if ($transaction->type === BankTransaction::TYPE_DEBIT) {
                $orgId = $bankAccount->organization_id;
                $amount = $this->absoluteAmount((string) $transaction->amount);
                $expenseSuggestions = $this->suggestExpenses($orgId, $transaction, $amount);
                $bestExpense = $expenseSuggestions->first();

                if ($bestExpense && $bestExpense->match_score >= MatchConfidence::AUTO_EXPENSE_THRESHOLD) {
                    try {
                        $this->reconcileWithExpense($transaction, $bestExpense);
                        $matched++;

                        continue;
                    } catch (\Exception $e) {
                        Log::warning('Auto-reconcile: skipped expense match', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $unmatched++;
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }
}
