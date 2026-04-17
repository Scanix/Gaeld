<?php

namespace App\Domains\Invoicing\Controllers;

use App\Domains\Invoicing\Actions\SendInvoiceAction;
use App\Domains\Invoicing\Actions\SendInvoiceReminderAction;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\QrBillValidationException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Support\QrBillValidationMessageFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Invoice communication: sending invoices and reminders to customers.
 */
class InvoiceCommunicationController extends Controller
{
    public function sendInvoice(
        Invoice $invoice,
        SendInvoiceAction $action,
        QrBillValidationMessageFormatter $messageFormatter,
    ): RedirectResponse {
        $this->authorize('send', $invoice);

        try {
            $action->execute($invoice->load('customer'));
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (QrBillValidationException $e) {
            return redirect()->back()->with('error', $messageFormatter->format($e->violations));
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.invoice_sent'));
    }

    public function sendReminder(Invoice $invoice, SendInvoiceReminderAction $action): RedirectResponse
    {
        $this->authorize('send', $invoice);

        try {
            $action->execute($invoice);
        } catch (InvalidInvoiceStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', __('app.reminder_sent'));
    }
}
