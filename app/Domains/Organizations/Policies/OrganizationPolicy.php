<?php

namespace App\Domains\Organizations\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function create(User $user): bool
    {
        $max = config('features.max_organizations_per_user', 10);

        return $user->organizations()->count() < $max;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationDelete);
    }

    public function manageUsers(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationManageUsers);
    }
}
