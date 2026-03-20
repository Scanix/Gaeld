<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\DeleteInvoiceAction;
use App\Domains\Invoicing\Actions\DuplicateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Actions\RecordPaymentAction;
use App\Domains\Invoicing\Actions\UpdateInvoiceAction;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceQuery;
use App\Domains\Accounting\Queries\VatRateQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    private const VALIDATION_RULES = [
        'number' => 'required|string|max:50',
        'issue_date' => 'required|date',
        'due_date' => 'required|date|after_or_equal:issue_date',
        'currency' => 'string|size:3',
        'notes' => 'nullable|string',
        'payment_terms' => 'nullable|string',
        'lines' => 'required|array|min:1',
        'lines.*.description' => 'required|string',
        'lines.*.quantity' => 'required|numeric|min:0.01',
        'lines.*.unit_price' => 'required|numeric|min:0',
    ];

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

    public function store(Request $request, CreateInvoiceAction $action, CurrentOrganization $currentOrg): RedirectResponse
    {
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

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created.');
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice->load(['customer', 'lines.vatRate', 'journalEntry.lines.account', 'payments.journalEntry']),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice->load('lines.vatRate'),
            'customers' => CustomerQuery::forSelect(),
            'vatRates' => VatRateQuery::active(),
        ]);
    }

    public function update(Request $request, Invoice $invoice, UpdateInvoiceAction $action): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate(array_merge(self::VALIDATION_RULES, [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('organization_id', $invoice->organization_id),
            ],
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $invoice->organization_id),
            ],
        ]));
        $validated['organization_id'] = $invoice->organization_id;

        $dto = UpdateInvoiceData::fromArray($validated);

        try {
            $action->execute($invoice, $dto);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice updated.');
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
            ->with('success', 'Invoice deleted.');
    }

    public function finalize(Invoice $invoice, FinalizeInvoiceAction $action): RedirectResponse
    {
        $this->authorize('update', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice finalized and posted to ledger.');
    }

    public function recordPayment(Request $request, Invoice $invoice, RecordPaymentAction $action): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => 'nullable|string|max:100',
            'bank_account_code' => 'nullable|string|max:20',
        ]);

        $dto = RecordPaymentData::fromArray($validated);

        try {
            $action->execute($invoice, $dto);
        } catch (InvalidInvoiceStateException|InvalidPaymentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Payment recorded.');
    }

    public function duplicate(Invoice $invoice, DuplicateInvoiceAction $action): RedirectResponse
    {
        $this->authorize('view', $invoice);

        $newInvoice = $action->execute($invoice);

        return redirect()->route('invoices.show', $newInvoice)
            ->with('success', 'Invoice duplicated.');
    }

    public function downloadQrPdf(Invoice $invoice, GenerateQrInvoicePdfAction $action, CurrentOrganization $currentOrg): HttpResponse
    {
        $this->authorize('view', $invoice);

        $organization = $currentOrg->get();
        $locale = $organization->locale ?? app()->getLocale();

        $pdf = $action->execute($invoice, $organization, $locale);

        $filename = 'invoice-' . ($invoice->number ?? $invoice->id) . '.pdf';

        return new HttpResponse($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
