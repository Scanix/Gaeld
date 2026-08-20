<?php

namespace App\Domains\Expenses\Policies;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for expense record management.
 */
class ExpensePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ExpensesView);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->belongsToOrganization($user, $expense)
            && $user->hasPermissionTo(Permission::ExpensesView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ExpensesCreate);
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($expense->archived_at !== null) {
            return false;
        }

        return $this->belongsToOrganization($user, $expense)
            && $user->hasPermissionTo(Permission::ExpensesEdit)
            && $expense->status->isEditable();
    }

    public function delete(User $user, Expense $expense): bool
    {
        if ($expense->archived_at !== null) {
            return false;
        }

        return $this->belongsToOrganization($user, $expense)
            && $user->hasPermissionTo(Permission::ExpensesDelete)
            && $expense->status->isDeletable();
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $this->belongsToOrganization($user, $expense)
            && $user->hasPermissionTo(Permission::ExpensesApprove);
    }
}
