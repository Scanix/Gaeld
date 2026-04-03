<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Services\InvoiceAccountingService;
use Illuminate\Support\Facades\Log;

/**
 * Records a payment against an invoice and updates its status (partial or fully paid).
 */
class RecordPaymentAction
{
    public function __construct(
        private InvoiceAccountingService $accountingService,
    ) {}

    public function execute(Invoice $invoice, RecordPaymentData $data): InvoicePayment
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Paid)) {
            throw new InvalidInvoiceStateException('Payments can only be recorded for sent or overdue invoices.');
        }

        $amount = $data->amount;
        $amountDue = $invoice->amountDue();

        if (bccomp($amount, $amountDue, 2) > 0) {
            throw new InvalidPaymentException("Payment amount ({$amount}) exceeds amount due ({$amountDue}).");
        }

        $payment = $this->accountingService->recordPayment($invoice, $data);

        Log::info('Invoice payment recorded', [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'amount_due_before' => $amountDue,
            'organization_id' => $invoice->organization_id,
        ]);

        return $payment;
    }
}
