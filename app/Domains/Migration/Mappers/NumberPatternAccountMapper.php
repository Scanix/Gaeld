<?php

namespace App\Domains\Migration\Mappers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Migration\Contracts\AccountMapperInterface;
use Illuminate\Support\Collection;

/**
 * Maps source accounts to target accounts by Swiss chart number patterns.
 *
 * Uses the standard Swiss KMU Kontenrahmen ranges:
 * 1xxx = Assets, 2xxx = Liabilities/Equity, 3xxx = Revenue,
 * 4xxx = Cost of goods, 5xxx-6xxx = Op. expenses, 8xxx-9xxx = Financial
 */
class NumberPatternAccountMapper implements AccountMapperInterface
{
    public function suggest(string $sourceCode, string $sourceName, Collection $targetAccounts): array
    {
        // Exact code match
        $exact = $targetAccounts->first(fn (Account $a) => $a->code === $sourceCode);
        if ($exact) {
            return ['account' => $exact, 'confidence' => 1.0];
        }

        // Match by number range (first 2 digits)
        $prefix = substr($sourceCode, 0, 2);
        $candidates = $targetAccounts->filter(fn (Account $a) => str_starts_with($a->code, $prefix));

        if ($candidates->isEmpty()) {
            // Try first digit only
            $firstDigit = substr($sourceCode, 0, 1);
            $candidates = $targetAccounts->filter(fn (Account $a) => str_starts_with($a->code, $firstDigit));
        }

        if ($candidates->isEmpty()) {
            return ['account' => null, 'confidence' => 0.0];
        }

        // Pick the closest code numerically
        $sourceNum = (int) $sourceCode;
        $closest = $candidates->sortBy(fn (Account $a) => abs((int) $a->code - $sourceNum))->first();

        $confidence = str_starts_with($closest->code, $prefix) ? 0.5 : 0.3;

        return [
            'account' => $closest,
            'confidence' => $confidence,
        ];
    }
}
