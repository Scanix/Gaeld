<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_absolute_amount_with_positive_value(): void
    {
        $this->assertSame('100.00', Money::absoluteAmount('100.00'));
    }

    public function test_absolute_amount_with_negative_value(): void
    {
        $this->assertSame('100.00', Money::absoluteAmount('-100.00'));
    }

    public function test_absolute_amount_with_zero(): void
    {
        $this->assertSame('0.00', Money::absoluteAmount('0'));
    }

    public function test_absolute_amount_preserves_decimal_precision(): void
    {
        $this->assertSame('99.95', Money::absoluteAmount('-99.95'));
        $this->assertSame('0.01', Money::absoluteAmount('0.01'));
    }
}
