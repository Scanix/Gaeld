<?php

namespace App\Domains\Banking\Policies;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::BankingView);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->organizations()->where('organizations.id', $bankAccount->organization_id)->exists()
            && $user->hasPermissionTo(Permission::BankingView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::BankingCreate);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->organizations()->where('organizations.id', $bankAccount->organization_id)->exists()
            && $user->hasPermissionTo(Permission::BankingEdit);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->organizations()->where('organizations.id', $bankAccount->organization_id)->exists()
            && $user->hasPermissionTo(Permission::BankingDelete)
            && $bankAccount->transactions()->doesntExist();
    }
}
