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
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Customers
 *
 * CRUD operations on customers. Customers are linked to invoices and contact persons.
 */
class CustomerApiController extends Controller
{
    /**
     * List customers
     *
     * Returns a paginated list of customers for the current organisation.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam search string Search by customer name or email. Example: ACME
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","type":"company","name":"ACME GmbH","email":"info@acme.ch","phone":"+41 44 123 45 67","address":"Bahnhofstrasse 1","city":"Zürich","postal_code":"8001","country":"CH","vat_number":"CHE-123.456.789","currency":"CHF","payment_terms":"30 days net","contact_persons":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"per_page":20,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = CustomerQuery::list($request);

        return CustomerResource::collection($customers);
    }

    /**
     * Show a customer
     *
     * Returns a single customer by UUID.
     *
     * @urlParam customer string required The customer UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","type":"company","name":"ACME GmbH","email":"info@acme.ch","phone":"+41 44 123 45 67","address":"Bahnhofstrasse 1","city":"Zürich","postal_code":"8001","country":"CH","vat_number":"CHE-123.456.789","currency":"CHF","payment_terms":"30 days net","contact_persons":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"Customer not found."}
     */
    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    /**
     * Create a customer
     *
     * Creates a new customer in the current organisation.
     *
     * @bodyParam name string required The customer name. Example: ACME GmbH
     * @bodyParam email string The customer email. Example: info@acme.ch
     * @bodyParam phone string Phone number. Example: +41 44 123 45 67
     * @bodyParam address string Street address. Example: Bahnhofstrasse 1
     * @bodyParam city string City. Example: Zürich
     * @bodyParam postal_code string Postal code. Example: 8001
     * @bodyParam country string ISO 3166-1 alpha-2 country code. Example: CH
     * @bodyParam vat_number string VAT registration number. Example: CHE-123.456.789
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     * @bodyParam payment_terms string Default payment terms. Example: 30 days net
     * @bodyParam internal_notes string Internal notes (not shown on invoices). No-example
     *
     * @response 201 scenario="Created" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","type":null,"name":"ACME GmbH","email":"info@acme.ch","phone":"+41 44 123 45 67","address":"Bahnhofstrasse 1","city":"Zürich","postal_code":"8001","country":"CH","vat_number":"CHE-123.456.789","currency":"CHF","payment_terms":"30 days net","contact_persons":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 422 scenario="Validation error" {"message":"The name field is required.","errors":{"name":["The name field is required."]}}
     */
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

    /**
     * Update a customer
     *
     * Updates an existing customer. Only provided fields are changed.
     *
     * @urlParam customer string required The customer UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @bodyParam name string The customer name. Example: ACME AG
     * @bodyParam email string The customer email. Example: billing@acme.ch
     * @bodyParam phone string Phone number. Example: +41 44 123 45 67
     * @bodyParam address string Street address. Example: Bahnhofstrasse 1
     * @bodyParam city string City. Example: Zürich
     * @bodyParam postal_code string Postal code. Example: 8001
     * @bodyParam country string ISO 3166-1 alpha-2 country code. Example: CH
     * @bodyParam vat_number string VAT registration number. Example: CHE-123.456.789
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     * @bodyParam payment_terms string Default payment terms. Example: 30 days net
     * @bodyParam internal_notes string Internal notes (not shown on invoices). No-example
     *
     * @response 200 scenario="Updated" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","type":"company","name":"ACME AG","email":"billing@acme.ch","phone":"+41 44 123 45 67","address":"Bahnhofstrasse 1","city":"Zürich","postal_code":"8001","country":"CH","vat_number":"CHE-123.456.789","currency":"CHF","payment_terms":"30 days net","contact_persons":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-20T14:30:00.000000Z"}}
     */
    public function update(UpdateCustomerApiRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $validated = $request->validated();

        $customer->update(
            UpdateCustomerData::fromArray($validated)->toArray()
        );

        return new CustomerResource($customer->fresh());
    }

    /**
     * Delete a customer
     *
     * Permanently deletes a customer. This will fail if the customer has linked invoices.
     *
     * @urlParam customer string required The customer UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 204 scenario="Deleted"
     * @response 404 scenario="Not found" {"message":"Customer not found."}
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        try {
            $customer->delete();
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[0] ?? '', ['23503', '23000'])) {
                return response()->json(
                    ['message' => __('app.customer_has_linked_records')],
                    409,
                );
            }

            throw $e;
        }

        return response()->json(null, 204);
    }
}
