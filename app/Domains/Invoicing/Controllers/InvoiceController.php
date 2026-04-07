<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Accounting\Queries\VatRateQuery;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Invoicing\Requests\StoreInvoiceRequest;
use App\Domains\Invoicing\Requests\UpdateInvoiceRequest;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Support\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invoice CRUD: creating, listing, viewing, editing and deleting invoices.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private FileUploadService $uploadService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceQuery::list($request),
            'query' => [
                'sort' => $request->input('sort', 'issue_date'),
                'direction' => $request->input('direction', 'desc'),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', ['type' => 'invoice']),
            ],
        ]);
    }

    public function create(Request $request, InvoiceNumberGenerator $numberGenerator, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Invoices/Create', [
            'customers' => CustomerQuery::forSelect(),
            'vatRates' => VatRateQuery::active(),
            'suggestedNumber' => $numberGenerator->next($currentOrg->id()),
            'defaultNotes' => $currentOrg->get()->default_invoice_notes ?? '',
            'defaultPaymentTermsDays' => $currentOrg->get()->default_payment_terms_days,
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action, CurrentOrganization $currentOrg, FinalizeInvoiceAction $finalizeAction): RedirectResponse
    {
        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        if ($request->hasFile('justificatif')) {
            $validated['justificatif_path'] = $this->uploadService->store(
                $request->file('justificatif'),
                "justificatifs/{$currentOrg->id()}",
            );
        }

        $dto = CreateInvoiceData::fromArray($validated);

        $invoice = $action->execute($dto);

        if ($request->boolean('finalize')) {
            $finalizeAction->execute($invoice);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_created'));
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice->load(['customer', 'lines.vatRate', 'journalEntry.lines.account', 'payments.journalEntry']),
            'justificatifUrl' => $invoice->justificatif_path
                ? route('invoices.justificatif.download', $invoice)
                : null,
            'bankAccounts' => BankAccount::where('organization_id', $invoice->organization_id)
                ->where('is_active', true)
                ->select('id', 'account_id', 'name', 'iban', 'currency')
                ->with('ledgerAccount:id,code')
                ->orderBy('name')
                ->get(),
            'creditNotes' => $invoice->creditNotes()
                ->select('id', 'number', 'total')
                ->get(),
            'relatedInvoice' => $invoice->relatedInvoice
                ? $invoice->relatedInvoice->only('id', 'number')
                : null,
            'reminderCount' => $invoice->reminder_count ?? 0,
            'lastRemindedAt' => $invoice->last_reminded_at?->toISOString(),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice->load('lines.vatRate'),
            'customers' => CustomerQuery::forSelect(),
            'vatRates' => VatRateQuery::active(),
            'justificatifUrl' => $invoice->justificatif_path
                ? route('invoices.justificatif.download', $invoice)
                : null,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, UpdateInvoiceAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $validated['organization_id'] = $invoice->organization_id;

        if ($request->hasFile('justificatif')) {
            $this->uploadService->delete($invoice->justificatif_path);
            $validated['justificatif_path'] = $this->uploadService->store(
                $request->file('justificatif'),
                "justificatifs/{$invoice->organization_id}",
            );
        }

        $dto = UpdateInvoiceData::fromArray($validated);

        try {
            $action->execute($invoice, $dto);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_updated'));
    }

    public function destroy(Invoice $invoice, DeleteInvoiceAction $action): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.index')
            ->with('success', __('app.invoice_deleted'));
    }
}
