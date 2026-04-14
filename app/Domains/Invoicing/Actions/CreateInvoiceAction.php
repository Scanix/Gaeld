<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new draft invoice with its line items.
 */
class CreateInvoiceAction
{
    public function __construct(
        private SyncInvoiceLinesAction $syncInvoiceLines,
    ) {}

    public function execute(CreateInvoiceData $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'organization_id' => $data->organizationId,
                'customer_id' => $data->customerId,
                'number' => $data->number,
                'status' => InvoiceStatus::Draft,
                'type' => InvoiceType::Invoice,
                'issue_date' => $data->issueDate,
                'due_date' => $data->dueDate,
                'subtotal' => 0,
                'vat_amount' => 0,
                'total' => 0,
                'currency' => $data->currency,
                'notes' => $data->notes,
                'payment_terms' => $data->paymentTerms,
            ]);

            $this->syncInvoiceLines->create($invoice, $data->lines);

            $invoice->recalculate();

            return $invoice->load('lines');
        });
    }
}
