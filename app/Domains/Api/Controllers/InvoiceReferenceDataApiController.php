<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Resources\InvoiceCatalogItemResource;
use App\Domains\Api\Resources\VatRateResource;
use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Invoice reference data
 */
class InvoiceReferenceDataApiController extends Controller
{
    /**
     * List VAT rates
     *
     * @response 200 {"data":[{"id":"437c3f2e-7d20-48ba-afd8-8b60ea170ac1","name":"Standard","code":"NORMAL","rate":"8.10","is_default":true,"is_active":true}],"links":{},"meta":{"current_page":1,"per_page":20,"total":1}}
     *
     * @queryParam per_page integer Page size, between 1 and 100. Example: 20
     */
    public function vatRates(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VatRate::class);

        return VatRateResource::collection(VatRate::query()
            ->orderBy('code')->orderBy('uuid')->paginate($this->pageSize($request)));
    }

    /**
     * Show a VAT rate by its public UUID
     *
     * @response 200 {"data":{"id":"437c3f2e-7d20-48ba-afd8-8b60ea170ac1","name":"Standard","code":"NORMAL","rate":"8.10","is_default":true,"is_active":true}}
     */
    public function vatRate(VatRate $vatRate, CurrentOrganization $organization): VatRateResource
    {
        abort_unless($vatRate->organization_id === $organization->id(), 404);
        $this->authorize('view', $vatRate);

        return new VatRateResource($vatRate);
    }

    /**
     * List invoice catalogue items
     *
     * @response 200 {"data":[{"id":"8fc1f733-cdc5-4e2d-9731-563c9cbbaeab","name":"Consultation","description":null,"default_unit_price":"100.00","default_vat_rate_id":"437c3f2e-7d20-48ba-afd8-8b60ea170ac1","is_active":true}],"links":{},"meta":{"current_page":1,"per_page":20,"total":1}}
     *
     * @queryParam per_page integer Page size, between 1 and 100. Example: 20
     */
    public function catalogItems(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InvoiceCatalogItem::class);

        return InvoiceCatalogItemResource::collection(InvoiceCatalogItem::query()
            ->with('defaultVatRate')->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->paginate($this->pageSize($request)));
    }

    /**
     * Show an invoice catalogue item
     *
     * @response 200 {"data":{"id":"8fc1f733-cdc5-4e2d-9731-563c9cbbaeab","name":"Consultation","description":null,"default_unit_price":"100.00","default_vat_rate_id":"437c3f2e-7d20-48ba-afd8-8b60ea170ac1","is_active":true}}
     */
    public function catalogItem(InvoiceCatalogItem $catalogItem, CurrentOrganization $organization): InvoiceCatalogItemResource
    {
        abort_unless($catalogItem->organization_id === $organization->id(), 404);
        $this->authorize('view', $catalogItem);

        return new InvoiceCatalogItemResource($catalogItem->load('defaultVatRate'));
    }

    private function pageSize(Request $request): int
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);

        return $request->integer('per_page', 20);
    }
}
