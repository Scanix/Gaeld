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

    public function test_divide_rounded_uses_half_up_rounding(): void
    {
        $this->assertSame('32.26', Money::divideRounded('1000.00', '31'));
    }

    public function test_normalize_rejects_fractional_cents(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::normalize('10.999');
    }

    public function test_percentage_uses_half_up_rounding(): void
    {
        $this->assertSame('53.01', Money::percentage('1000.10', '5.30'));
    }

    public function test_percentage_preserves_four_decimal_rate_precision(): void
    {
        $this->assertSame('12.35', Money::percentage('1234.56', '1.0001'));
    }
}
