<?php

namespace Tests\Unit\Services;

use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\DepreciationCalculator;
use Mockery;
use Tests\TestCase;

class DepreciationCalculatorTest extends TestCase
{
    private DepreciationCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DepreciationCalculator();
    }

    private function makeAsset(
        string $purchaseAmount,
        string $salvageValue,
        int $usefulLifeYears,
        DepreciationMethod $method,
        string $totalDepreciated = '0.00',
    ): FixedAsset {
        $nbv = bcsub($purchaseAmount, $totalDepreciated, 2);
        $isFullyDepreciated = bccomp($nbv, $salvageValue, 2) <= 0;

        $asset = Mockery::mock(FixedAsset::class)->makePartial();
        $asset->purchase_amount = $purchaseAmount;
        $asset->salvage_value = $salvageValue;
        $asset->useful_life_years = $usefulLifeYears;
        $asset->depreciation_method = $method;
        $asset->shouldReceive('netBookValue')->andReturn($nbv);
        $asset->shouldReceive('isFullyDepreciated')->andReturn($isFullyDepreciated);

        return $asset;
    }

    public function test_linear_depreciation(): void
    {
        // (10000 - 1000) / 5 = 1800.00
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::Linear);

        $this->assertSame('1800.00', $this->calculator->calculate($asset));
    }

    public function test_linear_depreciation_with_no_salvage_value(): void
    {
        // (12000 - 0) / 4 = 3000.00
        $asset = $this->makeAsset('12000.00', '0.00', 4, DepreciationMethod::Linear);

        $this->assertSame('3000.00', $this->calculator->calculate($asset));
    }

    public function test_declining_balance_depreciation(): void
    {
        // NBV = 10000, rate = 2/5 = 0.4, amount = 10000 * 0.4 = 4000.00
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::DecliningBalance);

        $this->assertSame('4000.00', $this->calculator->calculate($asset));
    }

    public function test_declining_balance_partial_depreciation(): void
    {
        // NBV = 6000 (10000 - 4000), rate = 2/5 = 0.4, amount = 6000 * 0.4 = 2400.00
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::DecliningBalance, '4000.00');

        $this->assertSame('2400.00', $this->calculator->calculate($asset));
    }

    public function test_declining_balance_does_not_depreciate_below_salvage(): void
    {
        // NBV = 1500 (10000 - 8500), rate = 2/5 = 0.4, amount = 1500*0.4 = 600
        // But 1500 - 600 = 900 < salvage 1000, so amount = 1500 - 1000 = 500
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::DecliningBalance, '8500.00');

        $this->assertSame('500.00', $this->calculator->calculate($asset));
    }

    public function test_fully_depreciated_asset_returns_zero(): void
    {
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::Linear, '9000.00');

        $this->assertSame('0.00', $this->calculator->calculate($asset));
    }

    public function test_monthly_amount_linear(): void
    {
        // Annual = 1800, monthly = 1800/12 = 150.00
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::Linear);

        $this->assertSame('150.00', $this->calculator->monthlyAmount($asset));
    }

    public function test_monthly_amount_fully_depreciated(): void
    {
        $asset = $this->makeAsset('10000.00', '1000.00', 5, DepreciationMethod::Linear, '9000.00');

        $this->assertSame('0.00', $this->calculator->monthlyAmount($asset));
    }
}
