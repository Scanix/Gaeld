<?php

namespace Tests\Traits;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds Spatie roles/permissions and assigns them to users in tests.
 */
trait WithOrganizationPermissions
{
    protected function seedPermissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function assignOrganizationRole(User $user, Organization $organization, string $roleName = 'owner'): void
    {
        app()[PermissionRegistrar::class]->setPermissionsTeamId($organization->id);
        $spatieRole = SpatieRole::findByName($roleName, 'web');
        $user->assignRole($spatieRole);
    }
}
