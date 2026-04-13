<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Requests\StoreSupplierApiRequest;
use App\Domains\Api\Requests\UpdateSupplierApiRequest;
use App\Domains\Api\Resources\SupplierResource;
use App\Domains\Contacts\DTOs\CreateSupplierData;
use App\Domains\Contacts\DTOs\UpdateSupplierData;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Contacts\Queries\SupplierQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Suppliers
 *
 * CRUD operations on suppliers. Suppliers are linked to expenses and contact persons.
 */
class SupplierApiController extends Controller
{
    /**
     * List suppliers
     *
     * Returns a paginated list of suppliers for the current organisation.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam search string Search by supplier name or email. Example: Digitec
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","name":"Digitec Galaxus AG","email":"billing@digitec.ch","phone":"+41 44 575 95 00","address":"Pfingstweidstrasse 60","city":"Zürich","postal_code":"8005","country":"CH","vat_number":"CHE-113.537.788","default_expense_category":"Office supplies","currency":"CHF","iban":null,"contact_persons":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"per_page":20,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = SupplierQuery::list($request);

        return SupplierResource::collection($suppliers);
    }

    /**
     * Show a supplier
     *
     * Returns a single supplier by UUID.
     *
     * @urlParam supplier string required The supplier UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","name":"Digitec Galaxus AG","email":"billing@digitec.ch","phone":"+41 44 575 95 00","address":"Pfingstweidstrasse 60","city":"Zürich","postal_code":"8005","country":"CH","vat_number":"CHE-113.537.788","default_expense_category":"Office supplies","currency":"CHF","iban":null,"contact_persons":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"Supplier not found."}
     */
    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return new SupplierResource($supplier);
    }

    /**
     * Create a supplier
     *
     * Creates a new supplier in the current organisation.
     *
     * @bodyParam name string required The supplier name. Example: Digitec Galaxus AG
     * @bodyParam email string The supplier email. Example: billing@digitec.ch
     * @bodyParam phone string Phone number. Example: +41 44 575 95 00
     * @bodyParam address string Street address. Example: Pfingstweidstrasse 60
     * @bodyParam city string City. Example: Zürich
     * @bodyParam postal_code string Postal code. Example: 8005
     * @bodyParam country string ISO 3166-1 alpha-2 country code. Example: CH
     * @bodyParam vat_number string VAT registration number. Example: CHE-113.537.788
     * @bodyParam default_expense_category string Default expense category. Example: Office supplies
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     * @bodyParam iban string IBAN number. Example: CH93 0076 2011 6238 5295 7
     * @bodyParam internal_notes string Internal notes. No-example
     *
     * @response 201 scenario="Created" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","name":"Digitec Galaxus AG","email":"billing@digitec.ch","created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 422 scenario="Validation error" {"message":"The name field is required.","errors":{"name":["The name field is required."]}}
     */
    public function store(StoreSupplierApiRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $supplier = Supplier::create(
            CreateSupplierData::fromArray($validated)->toArray()
        );

        return (new SupplierResource($supplier))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a supplier
     *
     * Updates an existing supplier. Only provided fields are changed.
     *
     * @urlParam supplier string required The supplier UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @bodyParam name string The supplier name. Example: Digitec AG
     * @bodyParam email string The supplier email. Example: billing@digitec.ch
     * @bodyParam phone string Phone number. Example: +41 44 575 95 00
     * @bodyParam address string Street address. Example: Pfingstweidstrasse 60
     * @bodyParam city string City. Example: Zürich
     * @bodyParam postal_code string Postal code. Example: 8005
     * @bodyParam country string ISO 3166-1 alpha-2 country code. Example: CH
     * @bodyParam vat_number string VAT registration number. Example: CHE-113.537.788
     * @bodyParam default_expense_category string Default expense category. Example: Travel
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     * @bodyParam iban string IBAN number. Example: CH93 0076 2011 6238 5295 7
     * @bodyParam internal_notes string Internal notes. No-example
     *
     * @response 200 scenario="Updated" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","name":"Digitec AG","email":"billing@digitec.ch","created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-20T14:30:00.000000Z"}}
     */
    public function update(UpdateSupplierApiRequest $request, Supplier $supplier): SupplierResource
    {
        $this->authorize('update', $supplier);

        $validated = $request->validated();

        $supplier->update(
            UpdateSupplierData::fromArray($validated)->toArray()
        );

        return new SupplierResource($supplier->fresh());
    }

    /**
     * Delete a supplier
     *
     * Permanently deletes a supplier. This will fail if the supplier has linked expenses.
     *
     * @urlParam supplier string required The supplier UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 204 scenario="Deleted"
     * @response 404 scenario="Not found" {"message":"Supplier not found."}
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        try {
            $supplier->delete();
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[0] ?? '', ['23503', '23000'])) {
                return response()->json(
                    ['message' => __('app.supplier_has_linked_records')],
                    409,
                );
            }

            throw $e;
        }

        return response()->json(null, 204);
    }
}
