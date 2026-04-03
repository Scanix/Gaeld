<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceMailerService;

/**
 * Sends a payment reminder e-mail for an overdue invoice.
 *
 * @deprecated Inject InvoiceMailerService directly for new code.
 */
class SendInvoiceReminderAction
{
    public function __construct(
        private InvoiceMailerService $mailerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return $this->mailerService->sendReminder($invoice);
    }
}
