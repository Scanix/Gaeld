<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Services\InvoiceService;

class RecordPaymentAction
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function execute(Invoice $invoice, array $data): InvoicePayment
    {
        if (! in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)) {
            throw new InvalidInvoiceStateException('Payments can only be recorded for sent or overdue invoices.');
        }

        $amount = (string) $data['amount'];
        $amountDue = $invoice->amountDue();

        if (bccomp($amount, $amountDue, 2) > 0) {
            throw new InvalidPaymentException("Payment amount ({$amount}) exceeds amount due ({$amountDue}).");
        }

        return $this->invoiceService->recordPayment($invoice, $data);
    }
}
