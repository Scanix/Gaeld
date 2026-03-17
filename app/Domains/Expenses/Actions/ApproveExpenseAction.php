<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;

class ApproveExpenseAction
{
    public function execute(Expense $expense): Expense
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            throw new \DomainException('Only pending expenses can be approved.');
        }

        $expense->update(['status' => ExpenseStatus::Approved->value]);

        return $expense->fresh();
    }
}
