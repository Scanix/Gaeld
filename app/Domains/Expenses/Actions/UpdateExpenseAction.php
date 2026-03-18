<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;

class UpdateExpenseAction
{
    public function execute(Expense $expense, array $data): Expense
    {
        if (! $expense->status->isEditable()) {
            throw new InvalidExpenseStateException('Posted expenses cannot be modified.');
        }

        $expense->update([
            'category' => $data['category'],
            'description' => $data['description'] ?? $expense->description,
            'amount' => $data['amount'],
            'vat_amount' => $data['vat_amount'] ?? $expense->vat_amount,
            'vat_rate_id' => $data['vat_rate_id'] ?? $expense->vat_rate_id,
            'date' => $data['date'],
            'vendor' => $data['vendor'] ?? $expense->vendor,
            'currency' => $data['currency'] ?? $expense->currency,
        ]);

        return $expense->fresh();
    }
}
