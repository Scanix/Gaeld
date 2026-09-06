<?php

namespace App\Domains\Expenses\Policies;

use App\Domains\Expenses\Models\RecurringExpense;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for recurring expense schedule management.
 */
class RecurringExpensePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ExpensesView);
    }

    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->belongsToOrganization($user, $recurringExpense)
            && $user->hasPermissionTo(Permission::ExpensesView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ExpensesCreate);
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->belongsToOrganization($user, $recurringExpense)
            && $user->hasPermissionTo(Permission::ExpensesEdit);
    }

    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->belongsToOrganization($user, $recurringExpense)
            && $user->hasPermissionTo(Permission::ExpensesDelete);
    }
}
