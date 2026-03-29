<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Creates a credit note linked to an existing invoice.
 */
class CreateCreditNoteAction
{
    public function __construct(
        private InvoiceNumberGenerator $numberGenerator,
        private SyncInvoiceLinesAction $syncInvoiceLines,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            throw new InvalidInvoiceStateException('Cannot create a credit note from a draft invoice.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->load('lines');

            $creditNote = Invoice::create([
                'organization_id' => $invoice->organization_id,
                'customer_id' => $invoice->customer_id,
                'number' => $this->numberGenerator->next($invoice->organization_id, 'CN'),
                'status' => InvoiceStatus::Draft,
                'type' => InvoiceType::CreditNote,
                'related_invoice_id' => $invoice->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'currency' => $invoice->currency,
                'notes' => "Credit note for invoice {$invoice->number}",
                'payment_terms' => $invoice->payment_terms,
                'subtotal' => 0,
                'vat_amount' => 0,
                'total' => 0,
            ]);

            $lines = $invoice->lines->map(
                fn ($line) => new InvoiceLineData(
                    description: 'Avoir: '.$line->description,
                    quantity: $line->quantity,
                    unitPrice: bcmul((string) $line->unit_price, '-1', 2),
                    vatRateId: $line->vat_rate_id,
                    sortOrder: $line->sort_order,
                )
            )->all();

            $this->syncInvoiceLines->create($creditNote, $lines);

            $creditNote->recalculate();

            return $creditNote->load('lines');
        });
    }
}
