<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;

class DuplicateInvoiceAction
{
    public function execute(Invoice $invoice): Invoice
    {
        $newInvoice = Invoice::create([
            'organization_id' => $invoice->organization_id,
            'client_id' => $invoice->client_id,
            'number' => $invoice->number . '-COPY',
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => $invoice->currency,
            'notes' => $invoice->notes,
            'payment_terms' => $invoice->payment_terms,
            'subtotal' => 0,
            'vat_amount' => 0,
            'total' => 0,
        ]);

        foreach ($invoice->lines as $index => $line) {
            $newLine = new InvoiceLine([
                'invoice_id' => $newInvoice->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
                'sort_order' => $line->sort_order ?? $index,
            ]);

            $newLine->calculateAmount();
        }

        $newInvoice->recalculate();

        return $newInvoice->load('lines');
    }
}
