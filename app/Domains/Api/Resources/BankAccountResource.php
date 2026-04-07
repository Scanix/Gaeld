<?php

namespace App\Domains\Api\Resources;

use App\Domains\Banking\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankAccount */
class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'iban' => $this->iban,
            'bank_name' => $this->bank_name,
            'currency' => $this->currency,
            'balance' => $this->balance,
            'is_active' => $this->is_active,
            'account_id' => $this->ledgerAccount?->uuid,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
