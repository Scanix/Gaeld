<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * Finalizes a draft invoice: assigns invoice number, posts journal entry, and marks as sent.
 */
class FinalizeInvoiceAction
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Sent)) {
            throw new InvalidInvoiceStateException("Only draft invoices can be finalized (current status: {$invoice->status->value}).");
        }

        if ($invoice->lines()->count() === 0) {
            throw new InvalidInvoiceStateException('Cannot finalize an invoice with no line items.');
        }

        $result = $this->invoiceService->postToLedger($invoice);

        Log::info('Invoice finalized', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $result->number,
            'organization_id' => $invoice->organization_id,
            'total' => (string) $result->total,
        ]);

        return $result;
    }
}
