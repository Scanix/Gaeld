<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;

class UpdateInvoiceAction
{
    public function execute(Invoice $invoice, UpdateInvoiceData $data): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new InvalidInvoiceStateException('Only draft invoices can be updated.');
        }

        $invoice->update([
            'customer_id' => $data->customerId,
            'number' => $data->number,
            'issue_date' => $data->issueDate,
            'due_date' => $data->dueDate,
            'currency' => $data->currency,
            'notes' => $data->notes,
            'payment_terms' => $data->paymentTerms,
        ]);

        // Replace line items
        $invoice->lines()->delete();

        foreach ($data->lines as $index => $lineData) {
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

        $invoice->recalculate();

        return $invoice->load('lines');
    }
}
