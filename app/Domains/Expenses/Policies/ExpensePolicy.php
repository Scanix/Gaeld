<?php

namespace App\Domains\Expenses\Policies;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Users\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->organizations()->where('organizations.id', $expense->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->status !== Expense::STATUS_POSTED;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && $expense->status === Expense::STATUS_PENDING;
    }
}
