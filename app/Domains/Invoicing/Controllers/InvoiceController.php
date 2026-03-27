<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Actions\CancelInvoiceAction;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Actions\DuplicateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Actions\RecordPaymentAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Exceptions\QrBillValidationException;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Requests\RecordPaymentRequest;
use App\Domains\Invoicing\Requests\StoreInvoiceRequest;
use App\Domains\Invoicing\Requests\UpdateInvoiceRequest;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Accounting\Queries\VatRateQuery;
use App\Http\Controllers\Controller;
use App\Support\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Invoices/Create', [
            'customers' => CustomerQuery::forSelect(),
            'vatRates' => VatRateQuery::active(),
        ]);
    }

    public function store(StoreInvoiceRequest $request, CreateInvoiceAction $action, CurrentOrganization $currentOrg): RedirectResponse
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
                ->select('id', 'name', 'iban', 'currency')
                ->orderBy('name')
                ->get(),
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

    public function finalize(Invoice $invoice, FinalizeInvoiceAction $action): RedirectResponse
    {
        $this->authorize('finalize', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_finalized'));
    }

    public function cancel(Invoice $invoice, CancelInvoiceAction $action): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_cancelled'));
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice, RecordPaymentAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $dto = RecordPaymentData::fromArray($validated);

        try {
            $action->execute($invoice, $dto);
        } catch (InvalidInvoiceStateException|InvalidPaymentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.payment_recorded'));
    }

    public function duplicate(Invoice $invoice, DuplicateInvoiceAction $action): RedirectResponse
    {
        $this->authorize('view', $invoice);

        $newInvoice = $action->execute($invoice);

        return redirect()->route('invoices.show', $newInvoice)
            ->with('success', __('app.invoice_duplicated'));
    }

    public function removeJustificatif(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->justificatif_path) {
            $this->uploadService->delete($invoice->justificatif_path);
            $invoice->update(['justificatif_path' => null]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.justificatif_removed'));
    }

    public function downloadJustificatif(Invoice $invoice): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $invoice->justificatif_path || ! Storage::disk('local')->exists($invoice->justificatif_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $invoice->justificatif_path,
            basename($invoice->justificatif_path),
        );
    }

    public function downloadQrPdf(Invoice $invoice, GenerateQrInvoicePdfAction $action, CurrentOrganization $currentOrg): HttpResponse|RedirectResponse
    {
        $this->authorize('view', $invoice);

        $organization = $currentOrg->get();
        $locale = $organization->locale ?? app()->getLocale();

        try {
            $pdf = $action->execute($invoice, $organization, $locale);
        } catch (QrBillValidationException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $filename = 'invoice-' . ($invoice->number ?? $invoice->id) . '.pdf';

        return new HttpResponse($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

}
