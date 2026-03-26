<?php

namespace App\Support;

class Money
{
    /**
     * Return the absolute value of a monetary amount using arbitrary-precision arithmetic.
     *
     * @param  string|int|float  $value  The monetary amount (e.g. '-123.45').
     * @return string  The absolute value as a string with 2 decimal places.
     *
     * @throws \InvalidArgumentException If the value is not numeric.
     */
    public static function absoluteAmount(string|int|float $value): string
    {
        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Money::absoluteAmount() expects a numeric value, got '{$value}'.");
        }

        return bccomp($value, '0', 2) < 0 ? bcmul($value, '-1', 2) : bcadd($value, '0', 2);
    }
}
