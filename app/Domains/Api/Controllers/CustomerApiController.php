<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Requests\StoreCustomerApiRequest;
use App\Domains\Api\Requests\UpdateCustomerApiRequest;
use App\Domains\Api\Resources\CustomerResource;
use App\Domains\Contacts\DTOs\CreateCustomerData;
use App\Domains\Contacts\DTOs\UpdateCustomerData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API: customer CRUD operations.
 */
class CustomerApiController extends Controller
{
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

    public function store(StoreCustomerApiRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $customer = Customer::create(
            CreateCustomerData::fromArray($validated)->toArray()
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCustomerApiRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $validated = $request->validated();

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
