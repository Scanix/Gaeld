<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Budget;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for budget management.
 */
class BudgetPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, Budget $budget): bool
    {
        return $this->belongsToOrganization($user, $budget)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, Budget $budget): bool
    {
        return $this->belongsToOrganization($user, $budget)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->belongsToOrganization($user, $budget)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
