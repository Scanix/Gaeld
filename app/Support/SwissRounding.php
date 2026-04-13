<?php

namespace App\Support;

/**
 * Swiss 5-centime rounding helper.
 *
 * Swiss francs are rounded to the nearest 0.05 CHF.
 * Example: 12.37 → 12.35, 12.38 → 12.40, 12.425 → 12.45
 */
class SwissRounding
{
    /**
     * Round an amount to the nearest 5 centimes (0.05 CHF).
     *
     * Algorithm: round(amount * 20) / 20
     * Uses bcmath for arbitrary-precision arithmetic.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public static function roundToFiveCents(string $amount): string
    {
        // Multiply by 20, round to nearest integer, divide by 20
        $multiplied = bcmul($amount, '20', 4);
        $rounded = (string) round((float) $multiplied);

        return bcdiv($rounded, '20', 2);
    }

    /**
     * Calculate the rounding difference (rounded - original).
     *
     * Positive = rounded up, negative = rounded down.
     *
     * @param  numeric-string  $original
     * @param  numeric-string  $rounded
     * @return numeric-string
     */
    public static function difference(string $original, string $rounded): string
    {
        return bcsub($rounded, $original, 2);
    }

    /**
     * Check if an amount needs Swiss rounding and return the adjustment.
     *
     * Returns null if no rounding is needed, or an array with 'rounded' and 'diff'.
     *
     * @param  numeric-string  $amount
     * @return array{rounded: numeric-string, diff: numeric-string}|null
     */
    public static function adjustment(string $amount): ?array
    {
        $rounded = self::roundToFiveCents($amount);
        $diff = self::difference($amount, $rounded);

        if (bccomp($diff, '0', 2) === 0) {
            return null;
        }

        return ['rounded' => $rounded, 'diff' => $diff];
    }
}
