<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }
}
