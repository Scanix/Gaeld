<?php

namespace App\Support;

/**
 * Arbitrary-precision money arithmetic helpers (bcmath wrapper).
 */
class Money
{
    private const SCALE = 2;

    private const INTERMEDIATE_SCALE = 4;

    /**
     * Ensure a value is a numeric-string suitable for bcmath.
     *
     * @return numeric-string
     */
    public static function of(string|int|float $value): string
    {
        /** @var numeric-string */
        return (string) $value;
    }

    /**
     * Return the absolute value of a monetary amount using arbitrary-precision arithmetic.
     *
     * @param  string|int|float  $value  The monetary amount (e.g. '-123.45').
     * @return numeric-string The absolute value as a string with 2 decimal places.
     *
     * @throws \InvalidArgumentException If the value is not numeric.
     */
    public static function absoluteAmount(string|int|float $value): string
    {
        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Money::absoluteAmount() expects a numeric value, got '{$value}'.");
        }

        /** @var numeric-string $value */
        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : bcadd($value, '0', self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function add(string $a, string $b): string
    {
        /** @var numeric-string $a */
        /** @var numeric-string $b */
        return bcadd($a, $b, self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function subtract(string $a, string $b): string
    {
        /** @var numeric-string $a */
        /** @var numeric-string $b */
        return bcsub($a, $b, self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function multiply(string $amount, string $factor): string
    {
        /** @var numeric-string $amount */
        /** @var numeric-string $factor */
        return bcmul($amount, $factor, self::INTERMEDIATE_SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function divide(string $amount, string $divisor): string
    {
        /** @var numeric-string $amount */
        /** @var numeric-string $divisor */
        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new \InvalidArgumentException('Money::divide() division by zero.');
        }

        return bcdiv($amount, $divisor, self::SCALE);
    }

    /**
     * @return numeric-string
     */
    public static function percentage(string $amount, string $rate): string
    {
        /** @var numeric-string $amount */
        /** @var numeric-string $rate */
        return bcdiv(bcmul($amount, $rate, self::INTERMEDIATE_SCALE), '100', self::SCALE);
    }

    /**
     * Compare two monetary values.
     *
     * Returns -1, 0, or 1 if $a is less than, equal to, or greater than $b.
     */
    public static function compare(string $a, string $b): int
    {
        /** @var numeric-string $a */
        /** @var numeric-string $b */
        return bccomp($a, $b, self::SCALE);
    }

    /**
     * Check if a monetary value is zero.
     */
    public static function isZero(string $value): bool
    {
        /** @var numeric-string $value */
        return bccomp($value, '0', self::SCALE) === 0;
    }

    /**
     * Check if a monetary value is negative.
     */
    public static function isNegative(string $value): bool
    {
        /** @var numeric-string $value */
        return bccomp($value, '0', self::SCALE) < 0;
    }

    /**
     * Check if a monetary value is positive (greater than zero).
     */
    public static function isPositive(string $value): bool
    {
        /** @var numeric-string $value */
        return bccomp($value, '0', self::SCALE) > 0;
    }

    /**
     * Negate a value (multiply by -1).
     *
     * @return numeric-string
     */
    public static function negate(string $value): string
    {
        /** @var numeric-string $value */
        return bcmul($value, '-1', self::SCALE);
    }

    /**
     * Multiply with standard 2-decimal scale (not intermediate).
     *
     * @return numeric-string
     */
    public static function multiply2(string $amount, string $factor): string
    {
        /** @var numeric-string $amount */
        /** @var numeric-string $factor */
        return bcmul($amount, $factor, self::SCALE);
    }

    /**
     * Divide with extended precision (4 decimals) for intermediate calculations.
     *
     * @return numeric-string
     */
    public static function divide4(string $amount, string $divisor): string
    {
        /** @var numeric-string $amount */
        /** @var numeric-string $divisor */
        return bcdiv($amount, $divisor, self::INTERMEDIATE_SCALE);
    }

    /**
     * Reduce an array of amounts by summing them.
     *
     * @param  array<array{amount: string}>  $items
     * @return numeric-string
     */
    public static function sumAmounts(array $items, string $initial = '0.00'): string
    {
        /** @var numeric-string */
        return array_reduce($items, fn (string $carry, array $item) => self::add($carry, $item['amount']), $initial);
    }

    /**
     * @return numeric-string
     */
    public static function zero(): string
    {
        return '0.00';
    }
}
