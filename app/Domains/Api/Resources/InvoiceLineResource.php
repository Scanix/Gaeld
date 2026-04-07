<?php

namespace App\Domains\Api\Resources;

use App\Domains\Invoicing\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceLine */
class InvoiceLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'vat_rate_id' => $this->vat_rate_id,
            'vat_amount' => $this->vat_amount,
            'sort_order' => $this->sort_order,
        ];
    }
}
