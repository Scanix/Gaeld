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
     * Return the absolute value of a monetary amount using arbitrary-precision arithmetic.
     *
     * @param  string|int|float  $value  The monetary amount (e.g. '-123.45').
     * @return string The absolute value as a string with 2 decimal places.
     *
     * @throws \InvalidArgumentException If the value is not numeric.
     */
    public static function absoluteAmount(string|int|float $value): string
    {
        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Money::absoluteAmount() expects a numeric value, got '{$value}'.");
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : bcadd($value, '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function multiply(string $amount, string $factor): string
    {
        return bcmul($amount, $factor, self::INTERMEDIATE_SCALE);
    }

    public static function divide(string $amount, string $divisor): string
    {
        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new \InvalidArgumentException('Money::divide() division by zero.');
        }

        return bcdiv($amount, $divisor, self::SCALE);
    }

    public static function percentage(string $amount, string $rate): string
    {
        return bcdiv(bcmul($amount, $rate, self::INTERMEDIATE_SCALE), '100', self::SCALE);
    }
}
