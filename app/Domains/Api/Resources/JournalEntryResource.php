<?php

namespace App\Domains\Api\Resources;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntry */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'status' => $this->is_posted ? 'posted' : 'draft',
            'is_posted' => $this->is_posted,
            'source' => $this->type ?? 'manual',
            'debit_total' => $this->totalDebit(),
            'credit_total' => $this->totalCredit(),
            'lines' => TransactionLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
