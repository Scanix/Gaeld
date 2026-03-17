<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Models\Expense;

class DeleteExpenseAction
{
    public function execute(Expense $expense): void
    {
        if (! $expense->status->isDeletable()) {
            throw new \DomainException('Only pending expenses can be deleted.');
        }

        $expense->delete();
    }
}
