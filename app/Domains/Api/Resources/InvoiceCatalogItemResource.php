<?php

namespace App\Domains\Api\Resources;

use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceCatalogItem */
class InvoiceCatalogItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'default_unit_price' => $this->default_unit_price,
            'default_vat_rate_id' => $this->defaultVatRate?->uuid,
            'is_active' => $this->is_active,
        ];
    }
}
