<?php

namespace App\Domains\Organizations\Policies;

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
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }
}
