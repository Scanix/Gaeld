<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for chart of accounts management.
 */
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
            && ! $account->is_system
            && $account->transactionLines()->doesntExist();
    }

    /**
     * Whether the user may change the `code` field of this account.
     * System accounts have their code locked to preserve app integrity.
     */
    public function updateCode(User $user, Account $account): bool
    {
        return $this->belongsToOrganization($user, $account)
            && $user->hasPermissionTo(Permission::AccountingEdit)
            && ! $account->is_system;
    }

    public function manage(User $user, Account $account): bool
    {
        return $this->belongsToOrganization($user, $account)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function closeYear(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }

    public function reopenYear(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingReopenYear);
    }
}
