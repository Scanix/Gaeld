<?php

namespace App\Domains\Organizations\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for organization-level operations (settings, members, billing).
 */
class OrganizationPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->userBelongsToOrg($user, $organization);
    }

    public function create(User $user): bool
    {
        $max = config('features.max_organizations_per_user', 10);

        return $user->organizations()->count() < $max;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->userBelongsToOrg($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->userBelongsToOrg($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationDelete);
    }

    public function manageUsers(User $user, Organization $organization): bool
    {
        return $this->userBelongsToOrg($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationManageUsers);
    }

    public function viewAuditLog(User $user, Organization $organization): bool
    {
        return $this->userBelongsToOrg($user, $organization)
            && $user->hasPermissionTo(Permission::OrganizationViewAuditLog);
    }

    /**
     * Check that the user is a member of the given organization.
     *
     * Unlike BasePolicy::belongsToOrganization(), this checks the Organization's
     * own ID (not organization_id) since the model IS the organization.
     */
    private function userBelongsToOrg(User $user, Organization $organization): bool
    {
        return $user->organizations()->where('organizations.id', $organization->id)->exists();
    }
}
