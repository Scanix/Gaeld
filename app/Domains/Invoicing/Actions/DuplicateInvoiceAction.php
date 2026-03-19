<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;

class DuplicateInvoiceAction
{
    public function __construct(
        private SyncInvoiceLinesAction $syncInvoiceLines,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        $newInvoice = Invoice::create([
            'organization_id' => $invoice->organization_id,
            'customer_id' => $invoice->customer_id,
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

        $this->syncInvoiceLines->create($newInvoice, $invoice->lines->map(
            fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
                'sort_order' => $line->sort_order,
            ]
        )->all());

        $newInvoice->recalculate();

        return $newInvoice->load('lines');
    }
}
