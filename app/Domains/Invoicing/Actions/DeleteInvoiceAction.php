<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;

/**
 * Soft-deletes a draft invoice.
 */
class DeleteInvoiceAction
{
    public function execute(Invoice $invoice): void
    {
        if (! $invoice->status->isDeletable()) {
            throw new InvalidInvoiceStateException('Only draft invoices can be deleted.');
        }

        $invoice->lines()->delete();
        $invoice->delete();
    }
}
