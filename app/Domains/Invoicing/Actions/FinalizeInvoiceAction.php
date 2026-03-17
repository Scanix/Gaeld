<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceService;

class FinalizeInvoiceAction
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new \DomainException('Only draft invoices can be finalized.');
        }

        return $this->invoiceService->finalizeInvoice($invoice);
    }
}
