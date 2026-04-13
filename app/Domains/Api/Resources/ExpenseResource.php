<?php

namespace App\Domains\Api\Resources;

use App\Domains\Expenses\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'vat_amount' => $this->vat_amount,
            'date' => $this->date->toDateString(),
            'vendor' => $this->vendor,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'supplier_id' => $this->supplier?->uuid,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
