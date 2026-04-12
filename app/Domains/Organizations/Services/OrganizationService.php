<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\DTOs\UpdateCommunicationsData;
use App\Domains\Organizations\DTOs\UpdateInvoiceSettingsData;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Enums\Role;
use App\Domains\Organizations\Events\MemberRemoved;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * CRUD operations for organizations and organization membership management.
 */
class OrganizationService
{
    // ──────────────────────────────────────────────────────────────
    //  CRUD
    // ──────────────────────────────────────────────────────────────

    /**
     * Create a new organization and attach the owner.
     */
    public function create(User $owner, CreateOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($owner, $data) {
            $org = Organization::create($data->toArray());

            $org->users()->attach($owner->id, ['role' => 'owner']);

            $this->assignSpatieRole($owner, $org, Role::Owner);

            return $org;
        });
    }

    public function update(Organization $organization, UpdateOrganizationData $data): Organization
    {
        $organization->update($data->toArray());

        return $organization;
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
    }

    public function updateInvoiceSettings(Organization $organization, UpdateInvoiceSettingsData $data): Organization
    {
        $organization->update($data->toArray());

        return $organization;
    }

    public function updateCommunications(Organization $organization, UpdateCommunicationsData $data): Organization
    {
        $organization->update($data->toArray());

        return $organization;
    }

    // ──────────────────────────────────────────────────────────────
    //  Membership
    // ──────────────────────────────────────────────────────────────

    /**
     * Add a member to an organization.
     */
    public function addMember(Organization $organization, User $user, string $role = 'member'): void
    {
        $organization->users()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);

        $spatieRole = Role::tryFrom($role) ?? Role::Member;
        $this->assignSpatieRole($user, $organization, $spatieRole);
    }

    /**
     * Remove a member from an organization.
     */
    public function removeMember(Organization $organization, User $user): void
    {
        $organization->users()->detach($user->id);

        app()[PermissionRegistrar::class]->setPermissionsTeamId($organization->id);
        $user->roles()->detach();

        MemberRemoved::dispatch($organization, $user);
    }

    /**
     * Change a member's role within an organization.
     */
    public function changeMemberRole(Organization $organization, User $user, Role $role): void
    {
        // Prevent removing the last owner
        if ($this->isLastOwner($organization, $user)) {
            throw ValidationException::withMessages([
                'role' => [__('app.cannot_change_last_owner')],
            ]);
        }

        $organization->users()->updateExistingPivot($user->id, ['role' => $role->value]);
        $this->assignSpatieRole($user, $organization, $role);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Check if removing/changing this user would leave the org without an owner.
     */
    public function isLastOwner(Organization $organization, User $user): bool
    {
        $ownerCount = $organization->users()
            ->wherePivot('role', 'owner')
            ->count();

        $isOwner = $organization->users()
            ->wherePivot('role', 'owner')
            ->where('users.id', $user->id)
            ->exists();

        return $isOwner && $ownerCount === 1;
    }

    private function assignSpatieRole(User $user, Organization $organization, Role $role): void
    {
        app()[PermissionRegistrar::class]->setPermissionsTeamId($organization->id);
        $user->syncRoles([$role->value]);
    }
}
