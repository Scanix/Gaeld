<?php

namespace App\Domains\Users\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::OrganizationManageUsers);
    }

    public function view(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        return $this->sharesOrganization($user, $target);
    }

    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    private function sharesOrganization(User $user, User $target): bool
    {
        $userOrgIds = $user->organizations()->pluck('organizations.id');

        return $target->organizations()->whereIn('organizations.id', $userOrgIds)->exists();
    }
}
