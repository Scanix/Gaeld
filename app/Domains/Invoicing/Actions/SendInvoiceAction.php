<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Mail\InvoiceMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an invoice to the customer by e-mail.
 */
class SendInvoiceAction
{
    public function __construct(
        private CurrentOrganization $currentOrg,
        private GenerateQrInvoicePdfAction $pdfAction,
    ) {}

    public function execute(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Sent && $invoice->status !== InvoiceStatus::Overdue) {
            throw new InvalidInvoiceStateException('Invoice must be finalized before sending.');
        }

        $customerEmail = $invoice->customer?->email;

        if (! $customerEmail) {
            throw new InvalidInvoiceStateException('Customer has no email address.');
        }

        $organization = $this->currentOrg->get();
        $locale = $organization->locale ?? app()->getLocale();

        $pdf = $this->pdfAction->execute($invoice, $organization, $locale);
        $filename = 'invoice-'.($invoice->number ?? $invoice->id).'.pdf';

        Mail::to($customerEmail)->send(new InvoiceMail($invoice, $organization, $pdf, $filename));
    }
}
