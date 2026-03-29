<?php

namespace App\Domains\Assets\Enums;

/** Depreciation calculation method for fixed assets. */
enum DepreciationMethod: string
{
    case Linear = 'linear';
    case DecliningBalance = 'declining_balance';
}
