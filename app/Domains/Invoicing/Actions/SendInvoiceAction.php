<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Mail\InvoiceMail;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

class SendInvoiceAction
{
    public function __construct(
        private CurrentOrganization $currentOrg,
    ) {}

    public function execute(Invoice $invoice): void
    {
        if ($invoice->status !== 'sent' && $invoice->status !== 'overdue') {
            throw new InvalidInvoiceStateException('Invoice must be finalized before sending.');
        }

        $customerEmail = $invoice->customer?->email;

        if (! $customerEmail) {
            throw new InvalidInvoiceStateException('Customer has no email address.');
        }

        $organization = $this->currentOrg->get();

        Mail::to($customerEmail)->send(new InvoiceMail($invoice, $organization));
    }
}
