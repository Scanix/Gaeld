<?php

namespace App\Domains\Accounting\Queries;

use App\Domains\Accounting\Models\VatRate;
use Illuminate\Database\Eloquent\Collection;

class VatRateQuery
{
    public static function active(): Collection
    {
        return VatRate::where('is_active', true)->get();
    }
}
