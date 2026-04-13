<?php

namespace App\Domains\Accounting\Queries;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class VatRateQuery
{
    /**
     * @return Collection<int, VatRate>
     */
    public static function active(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:reference"])->remember(
            "vat_rates_active:{$orgId}",
            3600,
            fn () => VatRate::where('is_active', true)->get()
        );
    }
}
