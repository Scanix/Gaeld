<?php

namespace App\Domains\Banking\Policies;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Users\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->organizations()->where('organizations.id', $bankAccount->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $this->view($user, $bankAccount);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->view($user, $bankAccount)
            && $bankAccount->transactions()->doesntExist();
    }
}
