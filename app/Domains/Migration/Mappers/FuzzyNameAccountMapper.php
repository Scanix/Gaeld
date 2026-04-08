<?php

namespace App\Domains\Migration\Mappers;

use App\Domains\Migration\Contracts\AccountMapperInterface;
use Illuminate\Support\Collection;

/**
 * Maps source accounts to target accounts via fuzzy name matching.
 *
 * Uses PHP's similar_text() for string similarity scoring, normalized
 * to a 0.0–1.0 confidence range.
 */
class FuzzyNameAccountMapper implements AccountMapperInterface
{
    public function suggest(string $sourceCode, string $sourceName, Collection $targetAccounts): array
    {
        $bestMatch = null;
        $bestConfidence = 0.0;

        $normalizedSource = $this->normalize($sourceName);

        foreach ($targetAccounts as $account) {
            $normalizedTarget = $this->normalize($account->name);

            similar_text($normalizedSource, $normalizedTarget, $percent);
            $nameConfidence = $percent / 100;

            // Boost confidence if account codes are similar
            $codeBoost = 0.0;
            if ($sourceCode === $account->code) {
                $codeBoost = 0.3;
            } elseif (str_starts_with($account->code, substr($sourceCode, 0, 2))) {
                $codeBoost = 0.1;
            }

            $confidence = min(1.0, $nameConfidence * 0.7 + $codeBoost);

            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestMatch = $account;
            }
        }

        return [
            'account' => $bestMatch,
            'confidence' => $bestConfidence,
        ];
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}
