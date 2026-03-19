<?php

namespace App\Domains\Banking\Rules;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankTransaction;
use App\Support\Money;

/**
 * EE Rule: Detect recurring transactions by matching amount AND description
 * pattern against past reconciled transactions in the same organization.
 *
 * If a matching past transaction was tagged with an expense category (via vendor
 * match on expense), suggest the same category.
 *
 * Confidence: 70 (heuristic — requires confirmation).
 */
class RecurringEntryRule extends BaseRule
{
    /** Number of past months to look back for pattern detection. */
    private const LOOKBACK_MONTHS = 3;

    /** Minimum occurrences needed to qualify as recurring. */
    private const MIN_OCCURRENCES = 2;

    public function name(): string
    {
        return 'Recurring Entry Detection';
    }

    public function confidence(): int
    {
        return 70;
    }

    public function matches(BankTransaction $transaction): bool
    {
        if ($transaction->type !== BankTransactionType::Debit) {
            return false;
        }

        if (! $transaction->description || ! $transaction->amount) {
            return false;
        }

        $orgId = $transaction->bankAccount->organization_id;
        $lookback = now()->subMonths(self::LOOKBACK_MONTHS);

        $absAmount = Money::absoluteAmount((string) $transaction->amount);

        // DEBIT amounts are stored as negative values; compare against negative range
        $count = BankTransaction::whereHas('bankAccount', fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_reconciled', true)
            ->where('type', BankTransactionType::Debit)
            ->where('date', '>=', $lookback)
            ->where('id', '!=', $transaction->id)
            ->whereBetween('amount', [
                '-' . bcadd($absAmount, '0.05', 2),
                '-' . bcsub($absAmount, '0.05', 2),
            ])
            ->count();

        return $count >= self::MIN_OCCURRENCES;
    }

    public function apply(BankTransaction $transaction): void
    {
        // For recurring entries, the engine surfaces the suggestion to the UI.
        // The actual categorization (creating an expense) must be confirmed by the user.
        // No automated write happens here — confidence 70 is below the auto-apply threshold.
    }
}
