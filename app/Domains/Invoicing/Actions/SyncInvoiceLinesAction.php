<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;

/**
 * Syncs invoice line items: creates or replaces all lines for an invoice.
 */
class SyncInvoiceLinesAction
{
    /**
     * @param  array<int, InvoiceLineData>  $lines
     */
    public function create(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $index => $lineData) {
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'description' => $lineData->description,
                'quantity' => $lineData->quantity,
                'unit_price' => $lineData->unitPrice,
                'vat_rate_id' => $lineData->vatRateId,
                'sort_order' => $lineData->sortOrder ?? $index,
            ]);

            $line->calculateAndSave();
        }
    }

    /**
     * @param  array<int, InvoiceLineData>  $lines
     */
    public function replace(Invoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();
        $this->create($invoice, $lines);
    }
}
