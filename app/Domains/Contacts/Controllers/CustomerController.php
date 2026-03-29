<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Contacts\Requests\StoreCustomerRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer CRUD with full-text search and soft-delete support.
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Contacts/Customers/Index', [
            'customers' => CustomerQuery::list($request),
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
        $this->authorize('create', Customer::class);

        return Inertia::render('Contacts/Customers/Create');
    }

    public function store(StoreCustomerRequest $request, CurrentOrganization $currentOrg): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $customer = Customer::create(CreateCustomerData::fromArray($validated)->toArray());

        if ($request->wantsJson()) {
            return response()->json(['customer' => $customer], 201);
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', __('app.customer_created'));
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Contacts/Customers/Show', [
            'customer' => $customer->load(['invoices', 'contactPersons']),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Contacts/Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validated();

        $customer->update(UpdateCustomerData::fromArray($validated)->toArray());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('app.customer_updated'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', __('app.customer_deleted'));
    }
}
