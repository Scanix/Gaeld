<?php

namespace Database\Seeders;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach (Permission::values() as $permission) {
            SpatiePermission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create roles and assign permissions
        foreach (Role::cases() as $role) {
            $spatieRole = SpatieRole::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);

            $spatieRole->syncPermissions($role->permissionValues());
        }
    }
}
