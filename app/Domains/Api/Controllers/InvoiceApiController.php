<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Requests\StoreInvoiceApiRequest;
use App\Domains\Api\Requests\UpdateInvoiceApiRequest;
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

class InvoiceApiController extends Controller
{
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
        StoreInvoiceApiRequest $request,
        CreateInvoiceAction $action,
        CurrentOrganization $currentOrg,
    ): JsonResponse {
        $this->authorize('create', Invoice::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $dto = CreateInvoiceData::fromArray($validated);
        $invoice = $action->execute($dto);

        return (new InvoiceResource($invoice->load(['customer', 'lines.vatRate'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateInvoiceApiRequest $request,
        Invoice $invoice,
        UpdateInvoiceAction $action,
        CurrentOrganization $currentOrg,
    ): InvoiceResource {
        $this->authorize('update', $invoice);

        $validated = $request->validated();
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
