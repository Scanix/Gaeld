<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Generates reconciliation suggestions (invoice and expense candidates)
 * for bank transactions.
 *
 * Delegates invoice matching to MatchingEngine. Handles expense scoring
 * using amount proximity and vendor name heuristics.
 */
class SuggestionService
{
    private const EXPENSE_SCORE_EXACT_AMOUNT = 50;

    private const EXPENSE_SCORE_VENDOR_MATCH = 30;

    public function __construct(
        private MatchingEngine $matchingEngine,
    ) {}

    /**
     * Get reconciliation suggestions for a paginated collection of transactions.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array<int, array{invoices: Collection, expenses: Collection, matches: Collection}>
     */
    public function generateSuggestionsForTransactions(iterable $transactions): array
    {
        $suggestions = [];

        foreach ($transactions as $transaction) {
            if (! $transaction->is_reconciled) {
                $suggestions[$transaction->id] = $this->generateSuggestions($transaction);
            }
        }

        return $suggestions;
    }

    /**
     * Get reconciliation suggestions for a single bank transaction.
     *
     * Uses MatchingEngine for invoice candidates and expense scoring for debit transactions.
     *
     * @return array{invoices: Collection, expenses: Collection, matches: Collection}
     */
    public function generateSuggestions(BankTransaction $transaction): array
    {
        $orgId = $transaction->bankAccount->organization_id;
        $amount = Money::absoluteAmount((string) $transaction->amount);

        $matches = $this->matchingEngine->findAndStoreMatches($transaction);

        $invoiceSuggestions = $matches->map(function ($match) {
            $invoice = $match->invoice->load(['customer']);
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

    /**
     * Find expense candidates for a debit transaction using amount and vendor heuristics.
     *
     * Scores each candidate by exact amount match (+50) and vendor name match (+30).
     */
    public function suggestExpenses(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        if ($transaction->type !== BankTransactionType::Debit) {
            return collect();
        }

        $results = Expense::where('organization_id', $orgId)
            ->whereIn('status', [ExpenseStatus::Pending->value, ExpenseStatus::Approved->value])
            ->where(function ($q) use ($amount, $transaction) {
                $q->whereBetween('amount', [
                    bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                    bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                ]);

                if ($transaction->creditor_name) {
                    $q->orWhere('vendor', 'like', '%' . $transaction->creditor_name . '%');
                }
            })
            ->limit(5)
            ->get();

        return $results->map(function ($expense) use ($amount, $transaction) {
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

            $expense->match_score = $score;

            return $expense;
        })->sortByDesc('match_score')->values();
    }
}
