<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::where('organization_id', $orgId)
            ->with('client')
            ->orderByDesc('issue_date')
            ->paginate(20);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Invoices/Create');
    }

    public function store(Request $request, CreateInvoiceAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
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
            'lines.*.vat_rate_id' => 'nullable|exists:vat_rates,id',
        ]);

        $this->authorize('create', Invoice::class);
        $validated['organization_id'] = $request->user()->resolveCurrentOrganization()->id;

        $invoice = $action->execute($validated, $validated['lines']);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created.');
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice->load(['client', 'lines.vatRate', 'journalEntry.lines.account']),
        ]);
    }

    public function finalize(Invoice $invoice, InvoiceService $invoiceService): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $invoice = $invoiceService->finalizeInvoice($invoice);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice finalized and posted to ledger.');
    }

    public function recordPayment(Request $request, Invoice $invoice, InvoiceService $invoiceService): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoiceService->recordPayment($invoice, $validated['amount']);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Payment recorded.');
    }
}
