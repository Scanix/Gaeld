<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Requests\StoreInvoiceApiRequest;
use App\Domains\Api\Requests\UpdateInvoiceApiRequest;
use App\Domains\Api\Resources\InvoiceResource;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Invoices
 *
 * CRUD operations on invoices. Invoices support line items, VAT rates, and payment tracking.
 */
class InvoiceApiController extends Controller
{
    /**
     * List invoices
     *
     * Returns a paginated list of invoices for the current organisation.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam sort string Sort field. Allowed: `issue_date`, `due_date`, `total`, `number`, `status`. Prefix with `-` for descending. Example: -issue_date
     * @queryParam filter[status] string Filter by status (e.g. `draft`, `sent`, `paid`). Example: sent
     * @queryParam filter[type] string Filter by type (e.g. `invoice`, `credit_note`). Example: invoice
     * @queryParam search string Search by invoice number or customer name. Example: INV-2025
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","number":"INV-2025-001","status":"sent","type":"invoice","related_invoice_id":null,"customer":{"id":"9c8f...","type":"company","name":"ACME GmbH"},"issue_date":"2025-01-15","due_date":"2025-02-14","subtotal":"1000.00","vat_amount":"81.00","total":"1081.00","currency":"CHF","notes":null,"payment_terms":"30 days net","amount_paid":"0.00","amount_due":"1081.00","lines":[],"payments":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}],"links":{"first":"...","last":"...","prev":null,"next":"..."},"meta":{"current_page":1,"from":1,"last_page":1,"per_page":20,"to":1,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = InvoiceQuery::list($request);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Show invoice
     *
     * Returns a single invoice with its customer, line items, and payments.
     *
     * @urlParam invoice string required The UUID of the invoice. Example: 9c8f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f...","number":"INV-2025-001","status":"sent","type":"invoice","related_invoice_id":null,"customer":{"id":"9c8f...","type":"company","name":"ACME GmbH","email":"info@acme.ch"},"issue_date":"2025-01-15","due_date":"2025-02-14","subtotal":"1000.00","vat_amount":"81.00","total":"1081.00","currency":"CHF","notes":null,"payment_terms":"30 days net","amount_paid":"0.00","amount_due":"1081.00","lines":[{"id":"uuid","description":"Consulting","quantity":"10.00","unit_price":"100.00","amount":"1000.00","vat_rate_id":"uuid","vat_amount":"81.00","sort_order":1}],"payments":[],"created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"No query results for model [Invoice]."}
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'lines.vatRate', 'payments']);

        return new InvoiceResource($invoice);
    }

    /**
     * Create invoice
     *
     * Creates a new invoice with line items. The invoice starts in `draft` status.
     *
     * @bodyParam customer_id string required UUID of an existing customer. Example: 9c8f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b
     * @bodyParam number string Optional invoice number (auto-generated if omitted). Example: INV-2025-042
     * @bodyParam issue_date string required Date in YYYY-MM-DD format. Example: 2025-01-15
     * @bodyParam due_date string Date in YYYY-MM-DD format, must be ≥ issue_date. Example: 2025-02-14
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     * @bodyParam notes string Notes displayed on the invoice.
     * @bodyParam payment_terms string Payment terms text. Example: 30 days net
     * @bodyParam lines object[] required At least one line item.
     * @bodyParam lines[].description string required Line description. Example: Consulting services
     * @bodyParam lines[].quantity number required Quantity (min 0.01). Example: 10
     * @bodyParam lines[].unit_price number required Unit price (min 0). Example: 100.00
     * @bodyParam lines[].vat_rate_id string UUID of a VAT rate in your organisation.
     *
     * @response 201 scenario="Created" {"data":{"id":"9c8f...","number":"INV-2025-042","status":"draft","type":"invoice","total":"1081.00","currency":"CHF","created_at":"2025-01-15T10:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 422 scenario="Validation error" {"message":"The given data was invalid.","errors":{"customer_id":["The customer id field is required."]}}
     */
    public function store(
        StoreInvoiceApiRequest $request,
        CreateInvoiceAction $action,
        CurrentOrganization $currentOrg,
        InvoiceNumberGenerator $numberGenerator,
    ): JsonResponse {
        $this->authorize('create', Invoice::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $shouldAutoGenerateNumber = empty($validated['number']);
        $maxAttempts = $shouldAutoGenerateNumber ? 3 : 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $payload = $validated;

            if ($shouldAutoGenerateNumber) {
                $payload['number'] = $numberGenerator->next($currentOrg->id());
            }

            // Resolve customer UUID to internal integer FK
            if (isset($payload['customer_id'])) {
                $payload['customer_id'] = Customer::where('uuid', $payload['customer_id'])
                    ->where('organization_id', $currentOrg->id())
                    ->value('id');
            }

            // Resolve vat_rate_id UUIDs to internal integer FKs in lines
            if (isset($payload['lines'])) {
                $payload['lines'] = $this->resolveLineVatRateUuids($payload['lines'], $currentOrg->id());
            }

            try {
                $dto = CreateInvoiceData::fromArray($payload);
                $invoice = $action->execute($dto);

                return (new InvoiceResource($invoice->load(['customer', 'lines.vatRate'])))
                    ->response()
                    ->setStatusCode(201);
            } catch (QueryException $exception) {
                if (! $shouldAutoGenerateNumber || ! $this->isInvoiceNumberConflict($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to create invoice after retrying invoice number generation.');
    }

    /**
     * Update invoice
     *
     * Updates an existing invoice. Only draft invoices can be modified.
     *
     * @urlParam invoice string required The UUID of the invoice. Example: 9c8f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b
     *
     * @bodyParam customer_id string UUID of an existing customer.
     * @bodyParam issue_date string Date in YYYY-MM-DD format.
     * @bodyParam due_date string Date in YYYY-MM-DD format.
     * @bodyParam currency string ISO 4217 currency code.
     * @bodyParam notes string Notes displayed on the invoice.
     * @bodyParam payment_terms string Payment terms text.
     * @bodyParam lines object[] Line items (replaces all existing lines).
     * @bodyParam lines[].description string required Line description.
     * @bodyParam lines[].quantity number required Quantity (min 0.01).
     * @bodyParam lines[].unit_price number required Unit price (min 0).
     * @bodyParam lines[].vat_rate_id string UUID of a VAT rate.
     *
     * @response 200 scenario="Updated" {"data":{"id":"9c8f...","number":"INV-2025-042","status":"draft","total":"2162.00","currency":"CHF"}}
     */
    public function update(
        UpdateInvoiceApiRequest $request,
        Invoice $invoice,
        UpdateInvoiceAction $action,
        CurrentOrganization $currentOrg,
    ): InvoiceResource {
        $this->authorize('update', $invoice);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        // Resolve customer UUID to internal integer FK
        if (isset($validated['customer_id'])) {
            $validated['customer_id'] = Customer::where('uuid', $validated['customer_id'])
                ->where('organization_id', $currentOrg->id())
                ->value('id');
        }

        // Resolve vat_rate_id UUIDs to internal integer FKs in lines
        if (isset($validated['lines'])) {
            $validated['lines'] = $this->resolveLineVatRateUuids($validated['lines'], $currentOrg->id());
        }

        $validated = $this->completeUpdatePayload($invoice, $validated, $currentOrg->id());

        $dto = UpdateInvoiceData::fromArray($validated);
        $action->execute($invoice, $dto);

        return new InvoiceResource($invoice->fresh(['customer', 'lines.vatRate']));
    }

    /**
     * Delete invoice
     *
     * Permanently deletes an invoice. Only draft invoices can be deleted.
     *
     * @urlParam invoice string required The UUID of the invoice. Example: 9c8f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b
     *
     * @response 204 scenario="Deleted"
     * @response 404 scenario="Not found" {"message":"No query results for model [Invoice]."}
     */
    public function destroy(Invoice $invoice, DeleteInvoiceAction $action): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $action->execute($invoice);

        return response()->json(null, 204);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function resolveLineVatRateUuids(array $lines, string $orgId): array
    {
        foreach ($lines as &$line) {
            if (isset($line['vat_rate_id'])) {
                $line['vat_rate_id'] = VatRate::where('uuid', $line['vat_rate_id'])
                    ->where('organization_id', $orgId)
                    ->value('id');
            }
        }

        return $lines;
    }

    /**
     * The update DTO still expects a full invoice payload, so sparse API updates
     * need to be hydrated from the existing draft invoice before mapping.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function completeUpdatePayload(Invoice $invoice, array $validated, string $organizationId): array
    {
        $invoice->loadMissing('lines');

        return [
            'organization_id' => $organizationId,
            'customer_id' => $validated['customer_id'] ?? $invoice->customer_id,
            'number' => $validated['number'] ?? $invoice->number,
            'issue_date' => $validated['issue_date'] ?? $invoice->issue_date->toDateString(),
            'due_date' => array_key_exists('due_date', $validated)
                ? $validated['due_date']
                : $invoice->due_date->toDateString(),
            'currency' => $validated['currency'] ?? $invoice->currency,
            'notes' => array_key_exists('notes', $validated)
                ? $validated['notes']
                : $invoice->notes,
            'payment_terms' => array_key_exists('payment_terms', $validated)
                ? $validated['payment_terms']
                : $invoice->payment_terms,
            'lines' => $validated['lines'] ?? $this->serializeExistingLines($invoice),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeExistingLines(Invoice $invoice): array
    {
        return $invoice->lines
            ->map(fn (InvoiceLine $line): array => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'type' => $line->type->value,
                'discount_type' => $line->discount_type,
                'vat_rate_id' => $line->vat_rate_id,
                'sort_order' => $line->sort_order,
            ])
            ->all();
    }

    private function isInvoiceNumberConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();

        return $sqlState === '23505'
            && str_contains($exception->getMessage(), 'invoices_organization_id_number_unique');
    }
}
