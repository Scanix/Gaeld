<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Invoicing\Models\Invoice;

class FinalizeInvoiceAction
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return $this->ledgerService->postInvoice($invoice);
    }
}
