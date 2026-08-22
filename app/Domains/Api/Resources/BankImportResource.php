<?php

namespace App\Domains\Api\Resources;

use App\Domains\Banking\Models\BankImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankImport */
class BankImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_account_id' => $this->bankAccount->uuid,
            'filename' => $this->filename,
            'format' => $this->format->value,
            'statement_id' => $this->statement_id,
            'transaction_count' => $this->transaction_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
