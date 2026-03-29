<?php

namespace App\Domains\Banking\Policies;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for bank account management.
 */
class BankAccountPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::BankingView);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $this->belongsToOrganization($user, $bankAccount)
            && $user->hasPermissionTo(Permission::BankingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::BankingCreate);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $this->belongsToOrganization($user, $bankAccount)
            && $user->hasPermissionTo(Permission::BankingEdit);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->belongsToOrganization($user, $bankAccount)
            && $user->hasPermissionTo(Permission::BankingDelete)
            && $bankAccount->transactions()->doesntExist();
    }
}
