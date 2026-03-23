<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * Create a new organization and attach the owner.
     */
    public function create(User $owner, CreateOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $org = Organization::create($data->toArray());

            $org->users()->attach($owner->id, ['role' => 'owner']);

            return $org;
        });
    }

    public function update(Organization $organization, UpdateOrganizationData $data): Organization
    {
        $organization->update($data->toArray());

        return $organization;
    }

    /**
     * Add a member to an organization.
     */
    public function addMember(Organization $organization, User $user, string $role = 'member'): void
    {
        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    /**
     * Remove a member from an organization.
     */
    public function removeMember(Organization $organization, User $user): void
    {
        $organization->users()->detach($user->id);
    }
}
