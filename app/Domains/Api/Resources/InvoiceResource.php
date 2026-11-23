<?php

namespace App\Domains\Api\Resources;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_source' => $this->external_source,
            'external_id' => $this->external_id,
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'tax_treatment' => $this->tax_treatment->value,
            'related_invoice_id' => $this->related_invoice_id,
            'customer' => new ContactResource($this->whenLoaded('customer')),
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal,
            'vat_amount' => $this->vat_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'payment_terms' => $this->payment_terms,
            'customer_reference' => $this->customer_reference,
            'purchase_order_reference' => $this->purchase_order_reference,
            'delivery_reference' => $this->delivery_reference,
            'amount_paid' => $this->amountPaid(),
            'amount_due' => $this->amountDue(),
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
