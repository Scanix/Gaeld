<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Mail\InvoiceMail;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * Sends invoice-related emails: initial invoice delivery and payment reminders.
 *
 * Consolidates SendInvoiceAction and SendInvoiceReminderAction logic.
 */
class InvoiceMailerService
{
    public function __construct(
        private CurrentOrganization $currentOrg,
        private GenerateQrInvoicePdfAction $pdfAction,
    ) {}

    public function sendInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Sent && $invoice->status !== InvoiceStatus::Overdue) {
            throw new InvalidInvoiceStateException('Invoice must be finalized before sending.');
        }

        $customerEmail = $this->resolveCustomerEmail($invoice);
        $organization = $this->currentOrg->get();
        $locale = $organization->locale ?? app()->getLocale();

        $pdf = $this->pdfAction->execute($invoice, $organization, $locale);
        $filename = 'invoice-'.($invoice->number ?? $invoice->id).'.pdf';

        Mail::to($customerEmail)->send(new InvoiceMail($invoice, $organization, $pdf, $filename));

        return $invoice;
    }

    public function sendReminder(Invoice $invoice): Invoice
    {
        if (! $invoice->isOverdue()) {
            throw new InvalidInvoiceStateException('Invoice is not overdue.');
        }

        $customerEmail = $this->resolveCustomerEmail($invoice);

        $invoice->increment('reminder_count');
        $invoice->update(['last_reminded_at' => now()]);

        Mail::to($customerEmail)->send(new InvoiceReminderMail($invoice->fresh()));

        return $invoice->fresh();
    }

    private function resolveCustomerEmail(Invoice $invoice): string
    {
        $email = $invoice->customer?->email;

        if (! $email) {
            throw new InvalidInvoiceStateException('Customer has no email address.');
        }

        return $email;
    }
}
