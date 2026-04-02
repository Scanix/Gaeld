<?php

namespace App\Domains\Assets\Enums;

/** Depreciation calculation method for fixed assets. */
enum DepreciationMethod: string
{
    case Linear = 'linear';
    case DecliningBalance = 'declining_balance';

    public function label(): string
    {
        return match ($this) {
            self::Linear => __('app.depreciation_method_linear'),
            self::DecliningBalance => __('app.depreciation_method_declining_balance'),
        };
    }
}
