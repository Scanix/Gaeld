<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;

class ApproveExpenseAction
{
    public function execute(Expense $expense): Expense
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            throw new InvalidExpenseStateException('Only pending expenses can be approved.');
        }

        $expense->update(['status' => ExpenseStatus::Approved]);

        return $expense->fresh();
    }
}
