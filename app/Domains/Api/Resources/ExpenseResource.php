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
            'net_amount' => $this->amount,
            'vat_amount' => $this->vat_amount,
            'gross_amount' => $this->gross_amount,
            'category_id' => $this->expense_category_id,
            'category_label' => $this->whenLoaded('expenseCategory', fn () => $this->expenseCategory->displayName(), $this->category),
            'expense_account' => $this->whenLoaded('expenseAccount', fn () => [
                'id' => $this->expenseAccount->id,
                'code' => $this->expenseAccount->code,
                'name' => $this->expenseAccount->display_name,
            ]),
            'date' => $this->date->toDateString(),
            'vendor' => $this->vendor,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'supplier_id' => $this->supplier?->uuid,
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
