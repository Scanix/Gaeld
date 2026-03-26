<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Resources\CustomerResource;
use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Queries\CustomerQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Controllers\Controller;

class CustomerApiController extends Controller
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
        'currency' => 'nullable|string|size:3',
        'payment_terms' => 'nullable|string|max:255',
        'internal_notes' => 'nullable|string',
    ];
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = CustomerQuery::list($request);

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate(self::VALIDATION_RULES);
        $validated['organization_id'] = app(\App\Domains\Organizations\Services\CurrentOrganization::class)->id();

        $customer = Customer::create(
            CreateCustomerData::fromArray($validated)->toArray()
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $validated = $request->validate(self::VALIDATION_RULES);

        $customer->update(
            UpdateCustomerData::fromArray($validated)->toArray()
        );

        return new CustomerResource($customer->fresh());
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->json(null, 204);
    }
}
