<?php

namespace App\Domains\Users\Policies;

use App\Domains\Users\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        $org = $user->resolveCurrentOrganization();

        return $org !== null
            && $user->organizations()
                ->where('organizations.id', $org->id)
                ->wherePivot('role', 'owner')
                ->exists();
    }

    public function view(User $user, User $target): bool
    {
        // Users can view themselves
        if ($user->id === $target->id) {
            return true;
        }

        // Owners can view members of their organization
        return $this->sharesOrganization($user, $target);
    }

    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return false; // Users cannot be deleted through the UI
    }

    private function sharesOrganization(User $user, User $target): bool
    {
        $userOrgIds = $user->organizations()->pluck('organizations.id');

        return $target->organizations()->whereIn('organizations.id', $userOrgIds)->exists();
    }
}
