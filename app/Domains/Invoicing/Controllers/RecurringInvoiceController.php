<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Contacts\Queries\ContactQuery;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\RecurringInvoice;
use App\Domains\Invoicing\Requests\RecurringInvoiceRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for recurring invoice schedules.
 */
class RecurringInvoiceController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RecurringInvoice::class);

        $recurringInvoices = RecurringInvoice::with('customer:id,name')
            ->orderByDesc('next_issue_date')
            ->paginate(20);

        return Inertia::render('Invoices/Recurring/Index', [
            'recurringInvoices' => $recurringInvoices,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RecurringInvoice::class);

        return Inertia::render('Invoices/Recurring/Create', [
            'customers' => ContactQuery::forSelect(),
            'frequencies' => array_map(
                fn (RecurrenceFrequency $f) => ['value' => $f->value, 'label' => ucfirst($f->value)],
                RecurrenceFrequency::cases(),
            ),
        ]);
    }

    public function store(RecurringInvoiceRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', RecurringInvoice::class);

        $validated = $request->validated();

        RecurringInvoice::create([
            'organization_id' => $currentOrg->id(),
            'customer_id' => $validated['customer_id'],
            'frequency' => $validated['frequency'],
            'next_issue_date' => $validated['next_issue_date'],
            'end_date' => $validated['end_date'] ?? null,
            'template_data' => $validated['template_data'],
        ]);

        return redirect()->route('invoices.recurring.index')
            ->with('success', __('app.recurring_invoice_created'));
    }

    public function edit(RecurringInvoice $recurring): Response
    {
        $this->authorize('update', $recurring);

        return Inertia::render('Invoices/Recurring/Edit', [
            'recurringInvoice' => $recurring->load('customer:id,name'),
            'customers' => ContactQuery::forSelect(),
            'frequencies' => array_map(
                fn (RecurrenceFrequency $f) => ['value' => $f->value, 'label' => ucfirst($f->value)],
                RecurrenceFrequency::cases(),
            ),
        ]);
    }

    public function update(RecurringInvoiceRequest $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $recurring->update($request->validated());

        return redirect()->route('invoices.recurring.index')
            ->with('success', __('app.recurring_invoice_updated'));
    }

    public function destroy(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('delete', $recurring);

        $recurring->delete();

        return redirect()->route('invoices.recurring.index')
            ->with('success', __('app.recurring_invoice_deleted'));
    }

    public function pause(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $recurring->update(['is_active' => false]);

        return back()->with('success', __('app.recurring_paused'));
    }

    public function resume(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $recurring->update(['is_active' => true]);

        return back()->with('success', __('app.recurring_resumed'));
    }
}
