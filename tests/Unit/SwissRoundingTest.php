<?php

namespace Tests\Unit;

use App\Support\SwissRounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SwissRoundingTest extends TestCase
{
    #[DataProvider('roundingProvider')]
    public function test_round_to_five_cents(string $input, string $expected): void
    {
        $this->assertSame($expected, SwissRounding::roundToFiveCents($input));
    }

    public static function roundingProvider(): array
    {
        return [
            'rounds 0.01 down to 0.00' => ['0.01', '0.00'],
            'rounds 0.02 down to 0.00' => ['0.02', '0.00'],
            'rounds 0.03 up to 0.05' => ['0.03', '0.05'],
            'rounds 0.04 up to 0.05' => ['0.04', '0.05'],
            'keeps 0.05 as is' => ['0.05', '0.05'],
            'rounds 0.06 down to 0.05' => ['0.06', '0.05'],
            'rounds 0.07 down to 0.05' => ['0.07', '0.05'],
            'rounds 0.08 up to 0.10' => ['0.08', '0.10'],
            'rounds 0.09 up to 0.10' => ['0.09', '0.10'],
            'keeps 0.10 as is' => ['0.10', '0.10'],
            'rounds 12.37 down to 12.35' => ['12.37', '12.35'],
            'rounds 12.38 up to 12.40' => ['12.38', '12.40'],
            'keeps 100.00 as is' => ['100.00', '100.00'],
            'keeps 99.95 as is' => ['99.95', '99.95'],
            'rounds 99.99 up to 100.00' => ['99.99', '100.00'],
            'handles zero' => ['0.00', '0.00'],
            'rounds 1234.56 down to 1234.55' => ['1234.56', '1234.55'],
            'rounds 1234.57 down to 1234.55' => ['1234.57', '1234.55'],
            'rounds 1234.58 up to 1234.60' => ['1234.58', '1234.60'],
        ];
    }

    public function test_difference_rounded_up(): void
    {
        $original = '12.38';
        $rounded = SwissRounding::roundToFiveCents($original);
        $diff = SwissRounding::difference($original, $rounded);

        $this->assertSame('12.40', $rounded);
        $this->assertSame('0.02', $diff);
    }

    public function test_difference_rounded_down(): void
    {
        $original = '12.37';
        $rounded = SwissRounding::roundToFiveCents($original);
        $diff = SwissRounding::difference($original, $rounded);

        $this->assertSame('12.35', $rounded);
        $this->assertSame('-0.02', $diff);
    }

    public function test_difference_no_rounding_needed(): void
    {
        $original = '100.00';
        $rounded = SwissRounding::roundToFiveCents($original);
        $diff = SwissRounding::difference($original, $rounded);

        $this->assertSame('100.00', $rounded);
        $this->assertSame('0.00', $diff);
    }

    public function test_difference_exact_five_cents(): void
    {
        $original = '50.25';
        $rounded = SwissRounding::roundToFiveCents($original);
        $diff = SwissRounding::difference($original, $rounded);

        $this->assertSame('50.25', $rounded);
        $this->assertSame('0.00', $diff);
    }
}
