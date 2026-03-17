<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;

class UpdateInvoiceAction
{
    public function execute(Invoice $invoice, array $data, array $lines): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new \DomainException('Only draft invoices can be updated.');
        }

        $invoice->update([
            'client_id' => $data['client_id'],
            'number' => $data['number'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'currency' => $data['currency'] ?? $invoice->currency,
            'notes' => $data['notes'] ?? $invoice->notes,
            'payment_terms' => $data['payment_terms'] ?? $invoice->payment_terms,
        ]);

        // Replace line items
        $invoice->lines()->delete();

        foreach ($lines as $index => $lineData) {
            $line = new InvoiceLine([
                'invoice_id' => $invoice->id,
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'unit_price' => $lineData['unit_price'],
                'vat_rate_id' => $lineData['vat_rate_id'] ?? null,
                'sort_order' => $lineData['sort_order'] ?? $index,
            ]);

            $line->calculateAmount();
        }

        $invoice->recalculate();

        return $invoice->load('lines');
    }
}
