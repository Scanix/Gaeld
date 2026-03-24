<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, Account $account): bool
    {
        return $user->organizations()->where('organizations.id', $account->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->organizations()->where('organizations.id', $account->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->organizations()->where('organizations.id', $account->organization_id)->exists()
            && $user->hasPermissionTo(Permission::AccountingDelete)
            && $account->transactionLines()->doesntExist();
    }
}
