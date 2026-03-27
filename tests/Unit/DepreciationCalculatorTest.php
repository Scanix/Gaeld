<?php

namespace Tests\Unit;

use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\DepreciationCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DepreciationCalculatorTest extends TestCase
{
    private DepreciationCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DepreciationCalculator;
    }

    #[Test]
    public function linear_depreciation_calculates_correctly(): void
    {
        $asset = $this->makeAsset(
            purchaseAmount: '10000.00',
            salvageValue: '1000.00',
            usefulLifeYears: 5,
            method: DepreciationMethod::Linear,
        );

        $annual = $this->calculator->calculate($asset);

        $this->assertSame('1800.00', $annual);
    }

    #[Test]
    public function linear_monthly_amount(): void
    {
        $asset = $this->makeAsset(
            purchaseAmount: '10000.00',
            salvageValue: '1000.00',
            usefulLifeYears: 5,
            method: DepreciationMethod::Linear,
        );

        $monthly = $this->calculator->monthlyAmount($asset);

        $this->assertSame('150.00', $monthly);
    }

    #[Test]
    public function declining_balance_calculates_correctly(): void
    {
        // Double-declining rate = 2/5 = 0.4
        // Year 1: 10000 * 0.4 = 4000
        $asset = $this->makeAsset(
            purchaseAmount: '10000.00',
            salvageValue: '1000.00',
            usefulLifeYears: 5,
            method: DepreciationMethod::DecliningBalance,
            totalDepreciated: '0.00',
        );

        $annual = $this->calculator->calculate($asset);

        $this->assertSame('4000.00', $annual);
    }

    #[Test]
    public function declining_balance_does_not_go_below_salvage(): void
    {
        // NBV = 1500, salvage = 1000, rate = 0.4
        // Raw depreciation = 1500 * 0.4 = 600, but NBV would be 900 < salvage
        // So capped at 1500 - 1000 = 500
        $asset = $this->makeAsset(
            purchaseAmount: '10000.00',
            salvageValue: '1000.00',
            usefulLifeYears: 5,
            method: DepreciationMethod::DecliningBalance,
            totalDepreciated: '8500.00',
        );

        $annual = $this->calculator->calculate($asset);

        $this->assertSame('500.00', $annual);
    }

    #[Test]
    public function fully_depreciated_returns_zero(): void
    {
        $asset = $this->makeAsset(
            purchaseAmount: '10000.00',
            salvageValue: '1000.00',
            usefulLifeYears: 5,
            method: DepreciationMethod::Linear,
            totalDepreciated: '9000.00',
        );

        $annual = $this->calculator->calculate($asset);

        $this->assertSame('0.00', $annual);
    }

    #[Test]
    public function zero_salvage_value_linear(): void
    {
        $asset = $this->makeAsset(
            purchaseAmount: '12000.00',
            salvageValue: '0.00',
            usefulLifeYears: 4,
            method: DepreciationMethod::Linear,
        );

        $annual = $this->calculator->calculate($asset);

        $this->assertSame('3000.00', $annual);
    }

    private function makeAsset(
        string $purchaseAmount,
        string $salvageValue,
        int $usefulLifeYears,
        DepreciationMethod $method,
        string $totalDepreciated = '0.00',
    ): FixedAsset {
        $asset = $this->createStub(FixedAsset::class);

        $asset->method('__get')->willReturnCallback(function (string $name) use ($purchaseAmount, $salvageValue, $usefulLifeYears, $method) {
            return match ($name) {
                'purchase_amount' => $purchaseAmount,
                'salvage_value' => $salvageValue,
                'useful_life_years' => $usefulLifeYears,
                'depreciation_method' => $method,
                default => null,
            };
        });

        $nbv = bcsub($purchaseAmount, $totalDepreciated, 2);
        $asset->method('totalDepreciated')->willReturn($totalDepreciated);
        $asset->method('netBookValue')->willReturn($nbv);
        $asset->method('isFullyDepreciated')->willReturn(bccomp($nbv, $salvageValue, 2) <= 0);

        return $asset;
    }
}
