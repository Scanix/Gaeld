<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use App\Domains\Invoicing\Services\SwissQrInvoiceService;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Finalizes a draft invoice: assigns invoice number, posts journal entry, and marks as sent.
 */
class FinalizeInvoiceAction
{
    public function __construct(
        private InvoiceAccountingService $accountingService,
        private SwissQrInvoiceService $qrService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Sent)) {
            throw new InvalidInvoiceStateException("Only draft invoices can be finalized (current status: {$invoice->status->value}).");
        }

        if ($invoice->lines()->count() === 0) {
            throw new InvalidInvoiceStateException('Cannot finalize an invoice with no line items.');
        }

        if (Money::isZero((string) $invoice->total)) {
            throw new InvalidInvoiceStateException(__('app.invoice_total_must_not_be_zero'));
        }

        $this->qrService->ensureQrReference($invoice, $invoice->organization);

        $result = $this->accountingService->postToLedger($invoice);

        Log::info('Invoice finalized', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $result->number,
            'organization_id' => $invoice->organization_id,
            'total' => (string) $result->total,
        ]);

        return $result;
    }
}
