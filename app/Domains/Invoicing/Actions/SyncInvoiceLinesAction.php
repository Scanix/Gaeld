<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\Enums\InvoiceLineType;
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
        // First pass: create and calculate all non-percentage-discount lines
        $createdLines = [];
        foreach ($lines as $index => $lineData) {
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'type' => $lineData->type->value,
                'discount_type' => $lineData->discountType,
                'description' => $lineData->description,
                'quantity' => $lineData->quantity,
                'unit_price' => $lineData->unitPrice,
                'vat_rate_id' => $lineData->vatRateId,
                'sort_order' => $lineData->sortOrder ?? $index,
            ]);

            if ($lineData->type === InvoiceLineType::Discount && $lineData->discountType === 'percentage') {
                $line->save();
            } else {
                $line->calculateAndSave();
            }
            $createdLines[] = $line;
        }

        // Second pass: compute percentage discount amounts based on item subtotal
        /** @var numeric-string $itemSubtotal */
        $itemSubtotal = (string) collect($createdLines)
            ->filter(fn (InvoiceLine $l) => $l->type === InvoiceLineType::Item)
            ->sum('amount');

        foreach ($createdLines as $line) {
            if ($line->type === InvoiceLineType::Discount && $line->discount_type === 'percentage') {
                $line->amount = bcmul(bcdiv((string) $line->unit_price, '100', 4), $itemSubtotal, 2);
                $vatRate = $line->vatRate;
                if ($vatRate) {
                    $line->vat_amount = bcmul($line->amount, bcdiv((string) $vatRate->rate, '100', 4), 2);
                } else {
                    $line->vat_amount = '0.00';
                }
                $line->save();
            }
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
