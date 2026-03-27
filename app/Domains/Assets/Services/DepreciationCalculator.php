<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Enums\DepreciationMethod;
use App\Domains\Assets\Models\FixedAsset;

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

        return bcdiv($annual, '12', 2);
    }

    /**
     * Linear depreciation: (purchase_amount - salvage_value) / useful_life_years
     */
    private function linear(FixedAsset $asset): string
    {
        $depreciableBase = bcsub($asset->purchase_amount, $asset->salvage_value, 2);

        return bcdiv($depreciableBase, (string) $asset->useful_life_years, 2);
    }

    /**
     * Declining balance (double-declining): net_book_value * (2 / useful_life_years)
     * Does not depreciate below salvage value.
     */
    private function decliningBalance(FixedAsset $asset): string
    {
        $nbv = $asset->netBookValue();

        $rate = bcdiv('2', (string) $asset->useful_life_years, 4);
        $amount = bcmul($nbv, $rate, 2);

        // Do not depreciate below salvage value
        $minNbv = bcsub($nbv, $amount, 2);
        if (bccomp($minNbv, $asset->salvage_value, 2) < 0) {
            $amount = bcsub($nbv, $asset->salvage_value, 2);
        }

        // Floor at zero
        if (bccomp($amount, '0', 2) < 0) {
            return '0.00';
        }

        return $amount;
    }
}
