<?php

namespace App\Domains\Migration\Contracts;

use App\Domains\Accounting\Models\Account;
use Illuminate\Support\Collection;

/**
 * Maps source account numbers/names from an external platform to
 * accounts in the organization's chart of accounts.
 *
 * Implementations can use fuzzy name matching, number pattern heuristics,
 * or any other strategy. The orchestrator picks the suggestion with
 * the highest confidence.
 */
interface AccountMapperInterface
{
    /**
     * Suggest a target account for the given source account.
     *
     * @param  Collection<int, Account>  $targetAccounts  Available accounts in the org
     * @return array{account: Account|null, confidence: float} confidence ∈ [0.0, 1.0]
     */
    public function suggest(
        string $sourceCode,
        string $sourceName,
        Collection $targetAccounts,
    ): array;
}
