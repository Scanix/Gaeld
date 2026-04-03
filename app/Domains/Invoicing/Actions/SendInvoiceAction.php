<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceMailerService;

/**
 * Sends an invoice to the customer by e-mail.
 *
 * @deprecated Inject InvoiceMailerService directly for new code.
 */
class SendInvoiceAction
{
    public function __construct(
        private InvoiceMailerService $mailerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return $this->mailerService->sendInvoice($invoice);
    }
}
