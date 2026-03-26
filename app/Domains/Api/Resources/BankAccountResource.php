<?php

namespace App\Domains\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iban' => $this->iban,
            'bank_name' => $this->bank_name,
            'currency' => $this->currency,
            'balance' => $this->balance,
            'is_active' => $this->is_active,
            'account_id' => $this->account_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
