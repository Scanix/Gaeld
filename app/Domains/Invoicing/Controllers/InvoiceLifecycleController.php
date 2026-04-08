<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Actions\CancelInvoiceAction;
use App\Domains\Invoicing\Actions\CreateCreditNoteAction;
use App\Domains\Invoicing\Actions\DuplicateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\Actions\PurgeInvoiceAction;
use App\Domains\Invoicing\Actions\RecordPaymentAction;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Requests\RecordPaymentRequest;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;

/**
 * Invoice state transitions: finalize, cancel, duplicate, credit notes, and payments.
 */
class InvoiceLifecycleController extends Controller
{
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
        } catch (ModelNotFoundException) {
            return redirect()->back()->with('error', __('app.account_not_found', ['code' => $validated['bank_account_code'] ?? '']));
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

    public function creditNote(Invoice $invoice, CreateCreditNoteAction $action): RedirectResponse
    {
        $this->authorize('view', $invoice);

        try {
            $creditNote = $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $creditNote)
            ->with('success', __('app.credit_note_created'));
    }

    public function purge(Invoice $invoice, PurgeInvoiceAction $action): RedirectResponse
    {
        $this->authorize('forceDelete', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.index')
            ->with('success', __('app.invoice_purged'));
    }
}
