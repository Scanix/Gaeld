<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;

/**
 * Updates a draft invoice and re-syncs its line items.
 */
class UpdateInvoiceAction
{
    public function __construct(
        private SyncInvoiceLinesAction $syncInvoiceLines,
    ) {}

    public function execute(Invoice $invoice, UpdateInvoiceData $data): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new InvalidInvoiceStateException('Only draft invoices can be updated.');
        }

        $customerSnapshot = $data->customerId === null
            ? null
            : Contact::withoutGlobalScope('organization')
                ->where('organization_id', $invoice->organization_id)
                ->findOrFail($data->customerId)
                ->toInvoiceSnapshot();

        $invoice->update([
            'customer_id' => $data->customerId,
            'customer_snapshot' => $customerSnapshot,
            'number' => $data->number,
            'issue_date' => $data->issueDate,
            'due_date' => $data->dueDate,
            'currency' => $data->currency,
            'notes' => $data->notes,
            'payment_terms' => $data->paymentTerms,
        ]);

        $this->syncInvoiceLines->replace($invoice, $data->lines);

        $invoice->recalculate();

        return $invoice->load('lines');
    }
}
