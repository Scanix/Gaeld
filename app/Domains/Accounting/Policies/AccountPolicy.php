<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Account;
use App\Domains\Users\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, Account $account): bool
    {
        return $user->organizations()->where('organizations.id', $account->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, Account $account): bool
    {
        return $this->view($user, $account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->view($user, $account)
            && $account->transactionLines()->doesntExist();
    }
}
