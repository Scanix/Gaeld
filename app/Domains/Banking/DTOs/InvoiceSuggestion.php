<?php

namespace App\Domains\Banking\DTOs;

use App\Domains\Banking\Enums\BankMatchType;
use App\Domains\Invoicing\Models\Invoice;

/**
 * Wraps an Invoice candidate with computed match metadata for the suggestion seam.
 *
 * Replaces the previous pattern of mutating Invoice Eloquent models with
 * ad-hoc dynamic properties (match_score, match_type, match_id). Match
 * metadata is held here; the Invoice model stays clean.
 */
readonly class InvoiceSuggestion implements \JsonSerializable
{
    public function __construct(
        public Invoice $invoice,
        public int $score,
        public BankMatchType $matchType,
        public int $matchId,
    ) {}

    /**
     * Serialise for Inertia/JSON: flatten invoice attributes plus match metadata.
     */
    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_merge($this->invoice->toArray(), [
            'match_score' => $this->score,
            'match_type' => $this->matchType,
            'match_id' => $this->matchId,
        ]);
    }
}
