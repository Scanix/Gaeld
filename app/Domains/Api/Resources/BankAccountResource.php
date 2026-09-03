<?php

namespace App\Domains\Api\Resources;

use App\Domains\Banking\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankAccount */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'iban' => $this->iban,
            'qr_iban' => $this->qr_iban,
            'bank_name' => $this->bank_name,
            'bic' => $this->bic,
            'currency' => $this->currency,
            'balance' => $this->balance,
            'is_active' => $this->is_active,
            'is_mixed_use' => $this->is_mixed_use,
            'is_default_for_invoicing' => $this->is_default_for_invoicing,
            'account_id' => $this->ledgerAccount?->uuid,
            'account_code' => $this->ledgerAccount?->code,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
