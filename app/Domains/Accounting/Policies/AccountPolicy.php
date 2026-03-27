<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class AccountPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, Account $account): bool
    {
        return $this->belongsToOrganization($user, $account)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->belongsToOrganization($user, $account)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->belongsToOrganization($user, $account)
            && $user->hasPermissionTo(Permission::AccountingDelete)
            && $account->transactionLines()->doesntExist();
    }
}
