<?php

namespace App\Domains\Accounting\Services;

/**
 * Swiss social charges (AVS/AI/APG) calculator for independent workers.
 *
 * Based on 2024/2025 OASI contribution rates published by BSV/OFAS.
 * Standard rate: 10.0% for annual income >= CHF 58,800.
 * Degressive scale applies for income between CHF 10,100 and CHF 58,800.
 */
final class SocialChargesService
{
    /** Standard AVS rate for independent workers */
    private const AVS_RATE = '0.081';

    /** Standard AI (disability insurance) rate */
    private const AI_RATE = '0.014';

    /** Standard APG (loss of earnings) rate */
    private const APG_RATE = '0.005';

    /** Combined standard rate */
    private const STANDARD_RATE = '0.100';

    /** Minimum income subject to contributions */
    private const MIN_INCOME = '10100';

    /** Income threshold for full rate */
    private const FULL_RATE_THRESHOLD = '58800';

    /** Minimum degressive rate */
    private const MIN_RATE = '0.05371';

    /**
     * Degressive scale brackets (upper bound => rate).
     * Simplified from the official BSV/OFAS table.
     */
    private const DEGRESSIVE_BRACKETS = [
        '17400' => '0.05543',
        '21500' => '0.05715',
        '25600' => '0.05887',
        '29700' => '0.06230',
        '33800' => '0.06573',
        '37900' => '0.06916',
        '42100' => '0.07259',
        '46200' => '0.07773',
        '50300' => '0.08287',
        '54500' => '0.08801',
        '58800' => '0.09315',
    ];

    /**
     * Calculate social charges for a given annual income.
     *
     * @return array{avs: string, ai: string, apg: string, total: string, rate: string, income: string}
     */
    public function calculate(string $annualIncome): array
    {
        if (bccomp($annualIncome, '0', 2) <= 0) {
            return [
                'avs' => '0.00',
                'ai' => '0.00',
                'apg' => '0.00',
                'total' => '0.00',
                'rate' => '0.00',
                'income' => $annualIncome,
            ];
        }

        if (bccomp($annualIncome, self::MIN_INCOME, 2) < 0) {
            return [
                'avs' => '0.00',
                'ai' => '0.00',
                'apg' => '0.00',
                'total' => '0.00',
                'rate' => '0.00',
                'income' => $annualIncome,
            ];
        }

        $effectiveRate = $this->effectiveRate($annualIncome);

        if (bccomp($effectiveRate, self::STANDARD_RATE, 5) >= 0) {
            // Full rate — split into AVS/AI/APG components
            $avs = bcmul($annualIncome, self::AVS_RATE, 2);
            $ai = bcmul($annualIncome, self::AI_RATE, 2);
            $apg = bcmul($annualIncome, self::APG_RATE, 2);
            $total = bcadd(bcadd($avs, $ai, 2), $apg, 2);
        } else {
            // Degressive rate — single combined rate, split proportionally
            $total = bcmul($annualIncome, $effectiveRate, 2);
            $ratioAvs = bcdiv(self::AVS_RATE, self::STANDARD_RATE, 6);
            $ratioAi = bcdiv(self::AI_RATE, self::STANDARD_RATE, 6);
            $avs = bcmul($total, $ratioAvs, 2);
            $ai = bcmul($total, $ratioAi, 2);
            $apg = bcsub($total, bcadd($avs, $ai, 2), 2);
        }

        return [
            'avs' => $avs,
            'ai' => $ai,
            'apg' => $apg,
            'total' => $total,
            'rate' => bcmul($effectiveRate, '100', 2),
            'income' => $annualIncome,
        ];
    }

    /**
     * Return the current rate table for display.
     *
     * @return array{avs: string, ai: string, apg: string, total: string, min_income: string, full_rate_threshold: string}
     */
    public function rates(): array
    {
        return [
            'avs' => bcmul(self::AVS_RATE, '100', 1),
            'ai' => bcmul(self::AI_RATE, '100', 1),
            'apg' => bcmul(self::APG_RATE, '100', 1),
            'total' => bcmul(self::STANDARD_RATE, '100', 1),
            'min_income' => self::MIN_INCOME,
            'full_rate_threshold' => self::FULL_RATE_THRESHOLD,
        ];
    }

    /**
     * Determine the effective contribution rate based on income.
     */
    private function effectiveRate(string $income): string
    {
        if (bccomp($income, self::FULL_RATE_THRESHOLD, 2) >= 0) {
            return self::STANDARD_RATE;
        }

        // Walk the degressive brackets
        $previousRate = self::MIN_RATE;

        foreach (self::DEGRESSIVE_BRACKETS as $upperBound => $rate) {
            if (bccomp($income, (string) $upperBound, 2) <= 0) {
                return $previousRate;
            }
            $previousRate = $rate;
        }

        return self::STANDARD_RATE;
    }
}
