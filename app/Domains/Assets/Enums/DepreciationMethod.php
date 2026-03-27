<?php

namespace App\Domains\Assets\Enums;

enum DepreciationMethod: string
{
    case Linear = 'linear';
    case DecliningBalance = 'declining_balance';
}
