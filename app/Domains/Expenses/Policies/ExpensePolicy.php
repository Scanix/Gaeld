<?php

namespace App\Domains\Expenses\Policies;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::ExpensesView);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->organizations()->where('organizations.id', $expense->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ExpensesView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::ExpensesCreate);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->organizations()->where('organizations.id', $expense->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ExpensesEdit)
            && $expense->status->isEditable();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->organizations()->where('organizations.id', $expense->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ExpensesDelete)
            && $expense->status->isDeletable();
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $user->organizations()->where('organizations.id', $expense->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ExpensesApprove);
    }
}
