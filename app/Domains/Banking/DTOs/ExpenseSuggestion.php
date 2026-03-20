<?php

namespace App\Domains\Banking\DTOs;

use App\Domains\Expenses\Models\Expense;

/**
 * Wraps an Expense candidate with a computed match score for the suggestion seam.
 *
 * Replaces the previous pattern of mutating Expense Eloquent models with
 * ad-hoc dynamic properties (match_score). The score is held here; the
 * Expense model stays clean.
 */
readonly class ExpenseSuggestion implements \JsonSerializable
{
    public function __construct(
        public Expense $expense,
        public int $score,
    ) {}

    /**
     * Serialise for Inertia/JSON: flatten expense attributes plus the score.
     */
    public function jsonSerialize(): array
    {
        return array_merge($this->expense->toArray(), ['match_score' => $this->score]);
    }
}
