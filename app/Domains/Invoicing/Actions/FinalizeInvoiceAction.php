<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;

class FinalizeInvoiceAction
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new \DomainException("Only draft invoices can be finalized (current status: {$invoice->status->value}).");
        }

        if ($invoice->lines()->count() === 0) {
            throw new \DomainException('Cannot finalize an invoice with no line items.');
        }

        return $this->ledgerService->postInvoice($invoice);
    }
}
