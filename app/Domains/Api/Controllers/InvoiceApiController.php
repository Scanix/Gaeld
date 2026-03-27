<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Resources\InvoiceResource;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InvoiceApiController extends Controller
{
    private const VALIDATION_RULES = [
        'number' => 'nullable|string|max:50',
        'issue_date' => 'required|date',
        'due_date' => 'nullable|date|after_or_equal:issue_date',
        'currency' => 'string|size:3',
        'notes' => 'nullable|string',
        'payment_terms' => 'nullable|string',
        'lines' => 'required|array|min:1',
        'lines.*.description' => 'required|string',
        'lines.*.quantity' => 'required|numeric|min:0.01',
        'lines.*.unit_price' => 'required|numeric|min:0',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = InvoiceQuery::list($request);

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'lines.vatRate', 'payments']);

        return new InvoiceResource($invoice);
    }

    public function store(
        Request $request,
        CreateInvoiceAction $action,
        CurrentOrganization $currentOrg,
    ): JsonResponse {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate(array_merge(self::VALIDATION_RULES, [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('organization_id', $currentOrg->id()),
            ],
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $currentOrg->id()),
            ],
        ]));
        $validated['organization_id'] = $currentOrg->id();

        $dto = CreateInvoiceData::fromArray($validated);
        $invoice = $action->execute($dto);

        return (new InvoiceResource($invoice->load(['customer', 'lines.vatRate'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        Request $request,
        Invoice $invoice,
        UpdateInvoiceAction $action,
        CurrentOrganization $currentOrg,
    ): InvoiceResource {
        $this->authorize('update', $invoice);

        $validated = $request->validate(array_merge(self::VALIDATION_RULES, [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('organization_id', $currentOrg->id()),
            ],
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $currentOrg->id()),
            ],
        ]));
        $validated['organization_id'] = $currentOrg->id();

        $dto = UpdateInvoiceData::fromArray($validated);
        $action->execute($invoice, $dto);

        return new InvoiceResource($invoice->fresh(['customer', 'lines.vatRate']));
    }

    public function destroy(Invoice $invoice, DeleteInvoiceAction $action): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $action->execute($invoice);

        return response()->json(null, 204);
    }
}
