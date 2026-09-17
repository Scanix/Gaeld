<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Mail\InvoiceMail;
use App\Domains\Invoicing\Mail\InvoiceReminderMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sends invoice-related emails: initial invoice delivery and payment reminders.
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

        Mail::to($customerEmail)->locale($locale)->send(new InvoiceMail($invoice, $organization, $pdf, $filename));

        return $invoice;
    }

    public function sendReminder(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->type !== InvoiceType::Invoice || ! $lockedInvoice->isOverdue()) {
                throw new InvalidInvoiceStateException('Invoice is not overdue.');
            }

            if ($lockedInvoice->isFullyPaid()) {
                throw new InvalidInvoiceStateException('Invoice has no outstanding balance.');
            }

            if ($lockedInvoice->last_reminded_at?->greaterThan(now()->subDays(7))) {
                throw new InvalidInvoiceStateException('A reminder was already sent within the last 7 days.');
            }

            $customerEmail = $this->resolveCustomerEmail($lockedInvoice);
            $reminderNumber = ((int) $lockedInvoice->reminder_count) + 1;
            $organization = $lockedInvoice->loadMissing('organization')->organization;
            $locale = $organization->locale ?? app()->getLocale();

            Mail::to($customerEmail)->locale($locale)->send(new InvoiceReminderMail($lockedInvoice, $organization, $reminderNumber));

            $lockedInvoice->update([
                'reminder_count' => $reminderNumber,
                'last_reminded_at' => now(),
            ]);

            return $lockedInvoice->fresh();
        });
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
