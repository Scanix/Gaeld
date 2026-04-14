<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new draft invoice by duplicating an existing one.
 */
class DuplicateInvoiceAction
{
    public function __construct(
        private SyncInvoiceLinesAction $syncInvoiceLines,
        private InvoiceNumberGenerator $numberGenerator,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $newInvoice = Invoice::create([
                'organization_id' => $invoice->organization_id,
                'customer_id' => $invoice->customer_id,
                'number' => $this->numberGenerator->next($invoice->organization_id),
                'status' => InvoiceStatus::Draft,
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
                fn ($line) => new InvoiceLineData(
                    description: $line->description,
                    quantity: $line->quantity,
                    unitPrice: $line->unit_price,
                    vatRateId: $line->vat_rate_id,
                    sortOrder: $line->sort_order,
                )
            )->all());

            $newInvoice->recalculate();

            return $newInvoice->load('lines');
        });
    }
}
