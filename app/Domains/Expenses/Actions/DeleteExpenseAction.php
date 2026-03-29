<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;

/**
 * Soft-deletes an expense (only allowed for pending expenses).
 */
class DeleteExpenseAction
{
    public function execute(Expense $expense): void
    {
        if (! $expense->status->isDeletable()) {
            throw new InvalidExpenseStateException('Only pending expenses can be deleted.');
        }

        $expense->delete();
    }
}
