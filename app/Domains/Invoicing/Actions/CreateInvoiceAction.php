<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Invoicing\Services\InvoiceService;

class CreateInvoiceAction
{
    public function execute(array $data, array $lines): Invoice
    {
        $invoice = Invoice::create([
            'organization_id' => $data['organization_id'],
            'client_id' => $data['client_id'],
            'number' => $data['number'],
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'currency' => $data['currency'] ?? 'CHF',
            'notes' => $data['notes'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'subtotal' => 0,
            'vat_amount' => 0,
            'total' => 0,
        ]);

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
