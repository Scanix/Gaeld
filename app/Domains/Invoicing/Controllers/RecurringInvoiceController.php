<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Contacts\Queries\CustomerQuery;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\RecurringInvoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for recurring invoice schedules.
 */
class RecurringInvoiceController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $recurringInvoices = RecurringInvoice::with('customer:id,name')
            ->orderByDesc('next_issue_date')
            ->paginate(20);

        return Inertia::render('Invoices/Recurring/Index', [
            'recurringInvoices' => $recurringInvoices,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Invoices/Recurring/Create', [
            'customers' => CustomerQuery::forSelect(),
            'frequencies' => array_map(
                fn (RecurrenceFrequency $f) => ['value' => $f->value, 'label' => ucfirst($f->value)],
                RecurrenceFrequency::cases(),
            ),
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'frequency' => ['required', 'string', 'in:weekly,monthly,quarterly,yearly'],
            'next_issue_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:next_issue_date'],
            'template_data' => ['required', 'array'],
            'template_data.lines' => ['required', 'array', 'min:1'],
            'template_data.lines.*.description' => ['required', 'string', 'max:500'],
            'template_data.lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'template_data.lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'template_data.notes' => ['nullable', 'string', 'max:2000'],
            'template_data.payment_terms' => ['nullable', 'string', 'max:255'],
            'template_data.currency' => ['nullable', 'string', 'size:3'],
        ]);

        RecurringInvoice::create([
            'organization_id' => $currentOrg->id(),
            'customer_id' => $validated['customer_id'],
            'frequency' => $validated['frequency'],
            'next_issue_date' => $validated['next_issue_date'],
            'end_date' => $validated['end_date'] ?? null,
            'template_data' => $validated['template_data'],
        ]);

        return redirect()->route('invoices.recurring.index')
            ->with('success', __('Recurring invoice created successfully.'));
    }

    public function edit(RecurringInvoice $recurring): Response
    {
        $this->authorize('update', Invoice::class);

        return Inertia::render('Invoices/Recurring/Edit', [
            'recurringInvoice' => $recurring->load('customer:id,name'),
            'customers' => CustomerQuery::forSelect(),
            'frequencies' => array_map(
                fn (RecurrenceFrequency $f) => ['value' => $f->value, 'label' => ucfirst($f->value)],
                RecurrenceFrequency::cases(),
            ),
        ]);
    }

    public function update(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('update', Invoice::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'frequency' => ['required', 'string', 'in:weekly,monthly,quarterly,yearly'],
            'next_issue_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_issue_date'],
            'template_data' => ['required', 'array'],
            'template_data.lines' => ['required', 'array', 'min:1'],
            'template_data.lines.*.description' => ['required', 'string', 'max:500'],
            'template_data.lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'template_data.lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'template_data.notes' => ['nullable', 'string', 'max:2000'],
            'template_data.payment_terms' => ['nullable', 'string', 'max:255'],
            'template_data.currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $recurring->update($validated);

        return redirect()->route('invoices.recurring.index')
            ->with('success', __('Recurring invoice updated successfully.'));
    }

    public function destroy(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('delete', Invoice::class);

        $recurring->delete();

        return redirect()->route('invoices.recurring.index')
            ->with('success', __('Recurring invoice deleted successfully.'));
    }

    public function pause(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('update', Invoice::class);

        $recurring->update(['is_active' => false]);

        return back()->with('success', __('app.recurring_paused'));
    }

    public function resume(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorize('update', Invoice::class);

        $recurring->update(['is_active' => true]);

        return back()->with('success', __('app.recurring_resumed'));
    }
}
