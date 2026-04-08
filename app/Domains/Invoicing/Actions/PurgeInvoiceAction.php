<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes a cancelled or soft-deleted invoice and its line items.
 */
class PurgeInvoiceAction
{
    public function execute(Invoice $invoice): void
    {
        if (! $invoice->trashed() && $invoice->status->value !== 'cancelled') {
            throw new InvalidInvoiceStateException('Only cancelled or already deleted invoices can be permanently removed.');
        }

        DB::transaction(function () use ($invoice) {
            $invoice->lines()->forceDelete();
            $invoice->forceDelete();
        });
    }
}
