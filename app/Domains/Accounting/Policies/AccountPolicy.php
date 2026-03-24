<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        $org = $user->resolveCurrentOrganization();
        $teamId = app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();
        $roles = $user->getRoleNames()->toArray();
        $hasPerm = false;
        try {
            $hasPerm = $user->hasPermissionTo(Permission::AccountingView);
        } catch (\Throwable $e) {
            \Log::error('AccountPolicy::viewAny permission check failed', [
                'user' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
        \Log::error('DEBUG AccountPolicy::viewAny', [
            'user' => $user->id,
            'email' => $user->email,
            'org' => $org?->id,
            'teamId' => $teamId,
            'roles' => $roles,
            'hasPerm' => $hasPerm,
            'result' => $org !== null && $hasPerm,
        ]);

        return $org !== null && $hasPerm;
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
