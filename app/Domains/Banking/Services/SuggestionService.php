<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\DTOs\ExpenseSuggestion;
use App\Domains\Banking\DTOs\InvoiceSuggestion;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Generates reconciliation suggestions (invoice and expense candidates)
 * for bank transactions.
 *
 * Delegates invoice matching to MatchingService. Handles expense scoring
 * using amount proximity and vendor name heuristics.
 */
class SuggestionService
{
    private const MAX_EXPENSE_CANDIDATES = 5;

    private const EXPENSE_SCORE_EXACT_AMOUNT = 50;

    private const EXPENSE_SCORE_VENDOR_MATCH = 30;

    public function __construct(
        private MatchingService $matchingService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Invoice Suggestions
    // ──────────────────────────────────────────────────────────────

    /**
     * Get reconciliation suggestions for a paginated collection of transactions.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array<string, array{invoices: Collection, expenses: Collection, matches: Collection}>
     */
    public function generateSuggestionsForTransactions(iterable $transactions): array
    {
        $suggestions = [];

        // Preload existing matches for all transactions in one query
        $transactionIds = collect($transactions)->pluck('id')->all();
        $existingMatches = BankMatch::whereIn('bank_transaction_id', $transactionIds)
            ->with('invoice.customer')
            ->get()
            ->groupBy('bank_transaction_id');

        foreach ($transactions as $transaction) {
            if ($transaction->is_reconciled) {
                continue;
            }

            $cachedMatches = $existingMatches->get($transaction->id, collect());
            $suggestions[$transaction->id] = $this->generateSuggestionsWithCache($transaction, $cachedMatches);
        }

        return $suggestions;
    }

    /**
     * Generate suggestions using pre-loaded match cache to minimize queries.
     *
     * @return array{invoices: Collection, expenses: Collection, matches: Collection}
     */
    private function generateSuggestionsWithCache(BankTransaction $transaction, Collection $cachedMatches): array
    {
        $amount = Money::absoluteAmount((string) $transaction->amount);

        // If we have existing unconfirmed matches, reuse them instead of re-querying
        $matches = $cachedMatches->isNotEmpty()
            ? $cachedMatches
            : $this->matchingService->findAndStoreMatches($transaction);

        $invoiceSuggestions = $matches->map(function ($match) {
            $invoice = $match->invoice?->load(['customer']);
            if (! $invoice) {
                return null;
            }

            return new InvoiceSuggestion(
                invoice: $invoice,
                score: $match->confidence,
                matchType: $match->match_type,
                matchId: $match->id,
            );
        })->filter()->sortByDesc(fn ($s) => $s->score)->values();

        $expenseSuggestions = $this->suggestExpenses(
            $transaction->bankAccount->organization_id,
            $transaction,
            $amount,
        );

        return [
            'invoices' => $invoiceSuggestions,
            'expenses' => $expenseSuggestions,
            'matches' => $matches,
        ];
    }

    /**
     * Get reconciliation suggestions for a single bank transaction.
     *
     * Uses MatchingService for invoice candidates and expense scoring for debit transactions.
     *
     * @return array{invoices: Collection, expenses: Collection, matches: Collection}
     */
    public function generateSuggestions(BankTransaction $transaction): array
    {
        $orgId = $transaction->bankAccount->organization_id;
        $amount = Money::absoluteAmount((string) $transaction->amount);

        $matches = $this->matchingService->findAndStoreMatches($transaction);

        $invoiceSuggestions = $matches->map(function ($match) {
            $invoice = $match->invoice?->load(['customer']);
            if (! $invoice) {
                return null;
            }

            return new InvoiceSuggestion(
                invoice: $invoice,
                score: $match->confidence,
                matchType: $match->match_type,
                matchId: $match->id,
            );
        })->filter()->sortByDesc(fn ($s) => $s->score)->values();

        $expenseSuggestions = $this->suggestExpenses($orgId, $transaction, $amount);

        return [
            'invoices' => $invoiceSuggestions,
            'expenses' => $expenseSuggestions,
            'matches' => $matches,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Expense Suggestions
    // ──────────────────────────────────────────────────────────────

    /**
     * Find expense candidates for a debit transaction using amount and vendor heuristics.
     *
     * Returns a collection of ExpenseSuggestion DTOs, each wrapping an Expense with
     * a computed score. Scored by exact amount match (+50) and vendor name match (+30).
     *
     * @return Collection<int, ExpenseSuggestion>
     */
    public function suggestExpenses(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        if ($transaction->type !== BankTransactionType::Debit) {
            return collect();
        }

        $candidateExpenses = Expense::where('organization_id', $orgId)
            ->where('status', ExpenseStatus::Posted)
            ->where(function ($q) use ($amount, $transaction) {
                $q->whereBetween('amount', [
                    bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                    bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                ]);

                if ($transaction->creditor_name) {
                    $q->orWhere('vendor', 'like', '%'.$transaction->creditor_name.'%');
                }
            })
            ->limit(self::MAX_EXPENSE_CANDIDATES)
            ->get();

        return $candidateExpenses->map(function ($expense) use ($amount, $transaction) {
            $score = 0;

            if (bccomp((string) $expense->amount, $amount, 2) === 0) {
                $score += self::EXPENSE_SCORE_EXACT_AMOUNT;
            }

            if ($transaction->creditor_name && $expense->vendor) {
                if (str_contains(strtolower($transaction->creditor_name), strtolower($expense->vendor))
                    || str_contains(strtolower($expense->vendor), strtolower($transaction->creditor_name))) {
                    $score += self::EXPENSE_SCORE_VENDOR_MATCH;
                }
            }

            return new ExpenseSuggestion(expense: $expense, score: $score);
        })->sortByDesc(fn ($s) => $s->score)->values();
    }
}
