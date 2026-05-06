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
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'related_invoice_id' => $this->related_invoice_id,
            'customer' => new ContactResource($this->whenLoaded('customer')),
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'subtotal' => $this->subtotal,
            'vat_amount' => $this->vat_amount,
            'total' => $this->total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'payment_terms' => $this->payment_terms,
            'amount_paid' => $this->amountPaid(),
            'amount_due' => $this->amountDue(),
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
