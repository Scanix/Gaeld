<?php

namespace App\Domains\Contacts\Controllers;

use App\Domains\Contacts\Actions\CreateCustomerAction;
use App\Domains\Contacts\Actions\UpdateCustomerAction;
use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private CreateCustomerAction $createCustomer,
        private UpdateCustomerAction $updateCustomer,
    ) {}

    private const VALIDATION_RULES = [
        'name' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:50',
        'address' => 'nullable|string|max:500',
        'city' => 'nullable|string|max:100',
        'postal_code' => 'nullable|string|max:10',
        'country' => 'nullable|string|size:2',
        'vat_number' => 'nullable|string|max:50',
        'currency' => 'nullable|string|size:3',
        'payment_terms' => 'nullable|string|max:255',
        'internal_notes' => 'nullable|string',
    ];

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

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate(self::VALIDATION_RULES);
        $validated['organization_id'] = app('current_organization')->id;

        $customer = $this->createCustomer->execute(CreateCustomerData::fromArray($validated));

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer created.');
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Contacts/Customers/Show', [
            'customer' => $customer->load('invoices'),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('Contacts/Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate(self::VALIDATION_RULES);

        $this->updateCustomer->execute($customer, UpdateCustomerData::fromArray($validated));

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted.');
    }
}
