<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Domains\Invoicing\Services\InvoiceAccountingService;

class UpdatePaymentDateAction
{
    public function __construct(
        private InvoiceAccountingService $accountingService,
    ) {}

    /**
     * @param  string  $paymentDate  ISO date (YYYY-MM-DD)
     */
    public function execute(Invoice $invoice, InvoicePayment $payment, string $paymentDate): InvoicePayment
    {
        return $this->accountingService->updatePaymentDate($invoice, $payment, $paymentDate);
    }
}
