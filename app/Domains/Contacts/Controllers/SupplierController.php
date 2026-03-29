<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateSupplierData;
use App\Domains\Contacts\DTOs\UpdateSupplierData;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Contacts\Queries\SupplierQuery;
use App\Domains\Contacts\Requests\StoreSupplierRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supplier CRUD with full-text search and soft-delete support.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        return Inertia::render('Contacts/Suppliers/Index', [
            'suppliers' => SupplierQuery::list($request),
            'query' => [
                'sort' => $request->input('sort', 'name'),
                'direction' => $request->input('direction', 'asc'),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Contacts/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request, CurrentOrganization $currentOrg): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $supplier = Supplier::create(CreateSupplierData::fromArray($validated)->toArray());

        if ($request->wantsJson()) {
            return response()->json(['supplier' => $supplier], 201);
        }

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', __('app.supplier_created'));
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        return Inertia::render('Contacts/Suppliers/Show', [
            'supplier' => $supplier->load(['expenses', 'contactPersons']),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Contacts/Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $validated = $request->validated();

        $supplier->update(UpdateSupplierData::fromArray($validated)->toArray());

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', __('app.supplier_updated'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', __('app.supplier_deleted'));
    }
}
