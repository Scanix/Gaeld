<?php

namespace App\Domains\Api\Resources;

use App\Domains\Contacts\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'vat_number' => $this->vat_number,
            'default_expense_category' => $this->default_expense_category,
            'currency' => $this->currency,
            'iban' => $this->iban,
            'contact_persons' => ContactPersonResource::collection($this->whenLoaded('contactPersons')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
