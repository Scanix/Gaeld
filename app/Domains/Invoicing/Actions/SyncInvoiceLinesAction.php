<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;

class SyncInvoiceLinesAction
{
    /**
     * @param array<int, array{description: string, quantity: string, unit_price: string, vat_rate_id?: ?string, sort_order?: int}> $lines
     */
    public function create(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $index => $lineData) {
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'unit_price' => $lineData['unit_price'],
                'vat_rate_id' => $lineData['vat_rate_id'] ?? null,
                'sort_order' => $lineData['sort_order'] ?? $index,
            ]);

            $line->calculateAndSave();
        }
    }

    /**
     * @param array<int, array{description: string, quantity: string, unit_price: string, vat_rate_id?: ?string, sort_order?: int}> $lines
     */
    public function replace(Invoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();
        $this->create($invoice, $lines);
    }
}