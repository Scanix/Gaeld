<?php

namespace App\Domains\Accounting\Queries;

use App\Domains\Accounting\Models\VatRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class VatRateQuery
{
    public static function active(): Collection
    {
        $orgId = app(\App\Domains\Organizations\Services\CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:reference"])->remember(
            "vat_rates_active:{$orgId}",
            3600,
            fn () => VatRate::where('is_active', true)->get()
        );
    }
}
