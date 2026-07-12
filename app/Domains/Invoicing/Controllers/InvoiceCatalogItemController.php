<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Invoicing\Queries\InvoiceCatalogItemQuery;
use App\Domains\Invoicing\Requests\StoreInvoiceCatalogItemRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class InvoiceCatalogItemController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', InvoiceCatalogItem::class);

        return response()->json(InvoiceCatalogItemQuery::all());
    }

    public function store(StoreInvoiceCatalogItemRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $currentOrg->get());

        $validated = $request->validated();

        $maxSort = InvoiceCatalogItem::where('organization_id', $currentOrg->id())->max('sort_order') ?? 0;

        InvoiceCatalogItem::create([
            'organization_id' => $currentOrg->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_unit_price' => $validated['default_unit_price'] ?? null,
            'default_vat_rate_id' => $validated['default_vat_rate_id'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('settings', ['tab' => 'invoice'])
            ->with('success', __('app.catalog_item_created'));
    }

    public function destroy(InvoiceCatalogItem $catalogItem, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $currentOrg->get());

        $catalogItem->delete();

        return redirect()->route('settings', ['tab' => 'invoice'])
            ->with('success', __('app.catalog_item_deleted'));
    }
}
