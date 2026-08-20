<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;

/**
 * Reverts an approved expense back to pending, e.g. when an approval was
 * made by mistake. No ledger effect: posting only happens once approved
 * expenses are explicitly posted, so there is nothing to reverse here.
 */
class UnapproveExpenseAction
{
    public function execute(Expense $expense): Expense
    {
        if (! $expense->status->canTransitionTo(ExpenseStatus::Pending)) {
            throw new InvalidExpenseStateException('Only approved expenses can be reverted to pending.');
        }

        $expense->update(['status' => ExpenseStatus::Pending]);

        return $expense->fresh();
    }
}
