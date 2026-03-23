<?php

namespace App\Support;

class Money
{
    public static function absoluteAmount(string $value): string
    {
        return bccomp($value, '0', 2) < 0 ? bcmul($value, '-1', 2) : $value;
    }
}
