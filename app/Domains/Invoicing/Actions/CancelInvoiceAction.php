<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;

class CancelInvoiceAction
{
    public function execute(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canTransitionTo(InvoiceStatus::Cancelled)) {
            throw new InvalidInvoiceStateException("Cannot cancel an invoice with status: {$invoice->status->value}.");
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        return $invoice->fresh();
    }
}
