<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Assets\Models\FixedAsset;
use App\Support\Money;

class DepreciationCalculator
{
    /**
     * Calculate the annual depreciation amount for a fixed asset.
     */
    public function calculate(FixedAsset $asset): string
    {
        if ($asset->isFullyDepreciated()) {
            return '0.00';
        }

        return match ($asset->depreciation_method) {
            DepreciationMethod::Linear => $this->linear($asset),
            DepreciationMethod::DecliningBalance => $this->decliningBalance($asset),
        };
    }

    /**
     * Calculate the monthly depreciation amount (annual / 12).
     */
    public function monthlyAmount(FixedAsset $asset): string
    {
        $annual = $this->calculate($asset);

        return Money::divide($annual, '12');
    }

    /**
     * Linear depreciation: (purchase_amount - salvage_value) / useful_life_years
     */
    private function linear(FixedAsset $asset): string
    {
        $depreciableBase = Money::subtract($asset->purchase_amount, $asset->salvage_value);

        return Money::divide($depreciableBase, (string) $asset->useful_life_years);
    }

    /**
     * Declining balance (double-declining): net_book_value * (2 / useful_life_years)
     * Does not depreciate below salvage value.
     */
    private function decliningBalance(FixedAsset $asset): string
    {
        $nbv = $asset->netBookValue();

        $rate = Money::divide4('2', (string) $asset->useful_life_years);
        $amount = Money::multiply2($nbv, $rate);

        // Do not depreciate below salvage value
        $minNbv = Money::subtract($nbv, $amount);
        if (Money::compare($minNbv, $asset->salvage_value) < 0) {
            $amount = Money::subtract($nbv, $asset->salvage_value);
        }

        // Floor at zero
        if (Money::isNegative($amount)) {
            return '0.00';
        }

        return $amount;
    }
}
