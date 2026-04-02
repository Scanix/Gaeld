<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Models\PersonalTransactionPattern;
use Illuminate\Support\Collection;

/**
 * Learns and suggests personal transaction patterns for mixed-use bank accounts.
 *
 * Tracks counterparty names from transactions marked as personal.
 * After a configurable threshold of consistent classifications,
 * suggests "Privé" on future imports with the same counterparty.
 */
class PersonalPatternService
{
    /** Minimum number of hits before suggesting a counterparty as personal. */
    private const SUGGESTION_THRESHOLD = 2;

    // ──────────────────────────────────────────────────────────────
    //  Recording
    // ──────────────────────────────────────────────────────────────

    /**
     * Record that a transaction was marked as personal.
     *
     * Extracts the counterparty name and increments the pattern counter.
     */
    public function recordPersonalTransaction(BankTransaction $transaction, string $orgId): void
    {
        $counterparty = $this->extractCounterparty($transaction);

        if ($counterparty === null) {
            return;
        }

        PersonalTransactionPattern::updateOrCreate(
            [
                'organization_id' => $orgId,
                'counterparty_name' => $counterparty,
            ],
            [
                'last_seen_at' => now(),
            ],
        )->increment('hit_count');
    }

    // ──────────────────────────────────────────────────────────────
    //  Queries
    // ──────────────────────────────────────────────────────────────

    /**
     * Get counterparty names that have been consistently marked as personal.
     *
     * @return Collection<int, string> Normalized counterparty names above threshold
     */
    public function getPersonalCounterparties(string $orgId): Collection
    {
        return PersonalTransactionPattern::where('organization_id', $orgId)
            ->where('hit_count', '>=', self::SUGGESTION_THRESHOLD)
            ->pluck('counterparty_name');
    }

    /**
     * Check if a transaction's counterparty matches a known personal pattern.
     */
    public function isLikelyPersonal(BankTransaction $transaction, string $orgId): bool
    {
        $counterparty = $this->extractCounterparty($transaction);

        if ($counterparty === null) {
            return false;
        }

        return PersonalTransactionPattern::where('organization_id', $orgId)
            ->where('counterparty_name', $counterparty)
            ->where('hit_count', '>=', self::SUGGESTION_THRESHOLD)
            ->exists();
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Extract and normalize the counterparty name from a transaction.
     *
     * Uses creditor_name for outgoing (debit) and debtor_name for incoming (credit).
     */
    private function extractCounterparty(BankTransaction $transaction): ?string
    {
        $name = $transaction->creditor_name ?? $transaction->debtor_name;

        if ($name === null || trim($name) === '') {
            return null;
        }

        return mb_strtolower(trim($name));
    }
}
