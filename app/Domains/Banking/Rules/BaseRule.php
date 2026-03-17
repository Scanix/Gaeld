<?php

namespace App\Domains\Banking\Rules;

use App\Domains\Banking\Models\BankTransaction;

/**
 * Abstract base for all automation rules.
 *
 * Rules are EE-only and run after CAMT import, invoice creation,
 * or expense creation via RuleEngineService.
 *
 * Implementations must:
 *   - Define `matches()` to determine applicability
 *   - Define `apply()` to execute the rule action
 *   - Be stateless and idempotent
 */
abstract class BaseRule
{
    /**
     * The name of this rule (used for logging and UI display).
     */
    abstract public function name(): string;

    /**
     * Determine if this rule should be applied to the given transaction.
     */
    abstract public function matches(BankTransaction $transaction): bool;

    /**
     * Apply the rule's automation logic.
     *
     * Must be idempotent — safe to call multiple times on the same transaction.
     */
    abstract public function apply(BankTransaction $transaction): void;

    /**
     * The rule's confidence score when it produces a match (0–100).
     *
     * Used by RuleEngineService to rank competing rules.
     */
    public function confidence(): int
    {
        return 70;
    }
}
