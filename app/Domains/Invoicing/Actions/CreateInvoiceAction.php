<?php

namespace App\Domains\Invoicing\Actions;

use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function execute(CreateInvoiceData $data, string $organizationId): Invoice
    {
        return DB::transaction(function () use ($data, $organizationId) {
            $invoice = Invoice::create([
                'organization_id' => $organizationId,
                'customer_id' => $data->customerId,
                'number' => $data->number,
                'status' => InvoiceStatus::Draft->value,
                'issue_date' => $data->issueDate,
                'due_date' => $data->dueDate,
                'currency' => $data->currency,
                'notes' => $data->notes,
                'payment_terms' => $data->paymentTerms,
                'subtotal' => 0,
                'vat_amount' => 0,
                'total' => 0,
            ]);

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
        });
    }
}
