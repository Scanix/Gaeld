<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function execute(array $data, array $lines): Invoice
    {
        return DB::transaction(function () use ($data, $lines) {
            $invoice = Invoice::create([
            'organization_id' => $data['organization_id'],
            'client_id' => $data['client_id'],
            'number' => $data['number'],
            'status' => InvoiceStatus::Draft->value,
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

            $line->calculateAndSave();
        }

            $invoice->recalculate();

            return $invoice->load('lines');
        });
    }
}
