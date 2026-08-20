<?php

namespace App\Domains\Invoicing\Queries;

use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;

class InvoiceCatalogItemQuery
{
    /**
     * Not cached: catalog items are edited directly from the invoice form, so
     * a newly added item must appear immediately rather than waiting on a TTL.
     *
     * @return Collection<int, InvoiceCatalogItem>
     */
    public static function forSelect(): Collection
    {
        return InvoiceCatalogItem::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, InvoiceCatalogItem>
     */
    public static function all(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return InvoiceCatalogItem::where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
