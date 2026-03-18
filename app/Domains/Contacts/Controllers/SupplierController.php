<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\Actions\CreateSupplierAction;
use App\Domains\Contacts\Actions\UpdateSupplierAction;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Contacts\Queries\SupplierQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    private const VALIDATION_RULES = [
        'name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'address' => 'nullable|string|max:500',
        'city' => 'nullable|string|max:100',
        'postal_code' => 'nullable|string|max:10',
        'country' => 'nullable|string|size:2',
        'vat_number' => 'nullable|string|max:50',
        'default_expense_category' => 'nullable|string|max:100',
        'currency' => 'nullable|string|size:3',
        'iban' => 'nullable|string|max:34',
        'internal_notes' => 'nullable|string',
    ];

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

    public function store(Request $request, CreateSupplierAction $action): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $validated = $request->validate(self::VALIDATION_RULES);
        $validated['organization_id'] = app('current_organization')->id;

        $supplier = $action->execute($validated);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        return Inertia::render('Contacts/Suppliers/Show', [
            'supplier' => $supplier->load('expenses'),
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Contacts/Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier, UpdateSupplierAction $action): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $validated = $request->validate(self::VALIDATION_RULES);

        $action->execute($supplier, $validated);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier deleted.');
    }
}
