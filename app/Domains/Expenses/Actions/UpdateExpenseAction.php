<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;

class UpdateExpenseAction
{
    public function execute(Expense $expense, UpdateExpenseData $data): Expense
    {
        if (! $expense->status->isEditable()) {
            throw new InvalidExpenseStateException('Posted expenses cannot be modified.');
        }

        $expense->update([
            'category' => $data->category,
            'description' => $data->description ?? $expense->description,
            'amount' => $data->amount,
            'vat_amount' => $data->vatAmount ?? $expense->vat_amount,
            'vat_rate_id' => $data->vatRateId ?? $expense->vat_rate_id,
            'date' => $data->date,
            'vendor' => $data->vendor ?? $expense->vendor,
            'receipt_path' => $data->receiptPath ?? $expense->receipt_path,
            'currency' => $data->currency ?? $expense->currency,
        ]);

        return $expense->fresh();
    }
}
