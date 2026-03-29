<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;

/**
 * Creates a new expense record in pending status.
 */
class CreateExpenseAction
{
    public function execute(CreateExpenseData $data): Expense
    {
        return Expense::create([
            ...$data->toArray(),
            'status' => ExpenseStatus::Pending,
            'vat_amount' => $data->vatAmount ?? 0,
        ]);
    }
}
