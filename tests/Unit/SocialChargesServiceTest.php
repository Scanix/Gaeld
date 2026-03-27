<?php

namespace Tests\Unit;

use App\Domains\Accounting\Services\SocialChargesService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SocialChargesServiceTest extends TestCase
{
    private SocialChargesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialChargesService;
    }

    public function test_zero_income_returns_zero_charges(): void
    {
        $result = $this->service->calculate('0');

        $this->assertSame('0.00', $result['avs']);
        $this->assertSame('0.00', $result['ai']);
        $this->assertSame('0.00', $result['apg']);
        $this->assertSame('0.00', $result['total']);
        $this->assertSame('0.00', $result['rate']);
    }

    public function test_negative_income_returns_zero_charges(): void
    {
        $result = $this->service->calculate('-5000');

        $this->assertSame('0.00', $result['total']);
    }

    public function test_income_below_minimum_returns_zero(): void
    {
        $result = $this->service->calculate('9000');

        $this->assertSame('0.00', $result['total']);
        $this->assertSame('0.00', $result['rate']);
    }

    public function test_high_income_uses_full_rate(): void
    {
        $result = $this->service->calculate('100000');

        // AVS = 100000 * 0.081 = 8100
        $this->assertSame('8100.00', $result['avs']);
        // AI = 100000 * 0.014 = 1400
        $this->assertSame('1400.00', $result['ai']);
        // APG = 100000 * 0.005 = 500
        $this->assertSame('500.00', $result['apg']);
        // Total = 10000
        $this->assertSame('10000.00', $result['total']);
        // Rate = 10.00%
        $this->assertSame('10.00', $result['rate']);
    }

    public function test_income_at_threshold_uses_full_rate(): void
    {
        $result = $this->service->calculate('58800');

        $this->assertSame('10.00', $result['rate']);
        $this->assertSame('5880.00', $result['total']);
    }

    public function test_degressive_income_uses_lower_rate(): void
    {
        // 15000 is between MIN_INCOME (10100) and first bracket (17400)
        $result = $this->service->calculate('15000');

        // Should use MIN_RATE = 5.371%
        $rate = $result['rate'];
        $this->assertTrue(bccomp($rate, '10.00', 2) < 0, 'Rate should be below 10%');
        $this->assertTrue(bccomp($rate, '0.00', 2) > 0, 'Rate should be above 0%');
    }

    public function test_components_sum_to_total(): void
    {
        $result = $this->service->calculate('100000');

        $componentSum = bcadd(bcadd($result['avs'], $result['ai'], 2), $result['apg'], 2);
        $this->assertSame($result['total'], $componentSum);
    }

    public function test_degressive_components_sum_to_total(): void
    {
        $result = $this->service->calculate('30000');

        $componentSum = bcadd(bcadd($result['avs'], $result['ai'], 2), $result['apg'], 2);
        $this->assertSame($result['total'], $componentSum);
    }

    public function test_rates_returns_rate_table(): void
    {
        $rates = $this->service->rates();

        $this->assertSame('8.1', $rates['avs']);
        $this->assertSame('1.4', $rates['ai']);
        $this->assertSame('0.5', $rates['apg']);
        $this->assertSame('10.0', $rates['total']);
        $this->assertSame('10100', $rates['min_income']);
        $this->assertSame('58800', $rates['full_rate_threshold']);
    }

    public function test_income_preserves_input(): void
    {
        $result = $this->service->calculate('75000.50');

        $this->assertSame('75000.50', $result['income']);
    }

    #[DataProvider('highIncomeProvider')]
    public function test_various_high_incomes(string $income, string $expectedTotal): void
    {
        $result = $this->service->calculate($income);

        $this->assertSame($expectedTotal, $result['total']);
        $this->assertSame('10.00', $result['rate']);
    }

    public static function highIncomeProvider(): array
    {
        return [
            '60k' => ['60000', '6000.00'],
            '80k' => ['80000', '8000.00'],
            '120k' => ['120000', '12000.00'],
            '200k' => ['200000', '20000.00'],
        ];
    }
}
