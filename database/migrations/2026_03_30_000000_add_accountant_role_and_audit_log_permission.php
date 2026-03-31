<?php

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create any new permissions (organization.view-audit-log)
        foreach (Permission::values() as $permission) {
            SpatiePermission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create roles (including new accountant) and sync all permissions
        foreach (Role::cases() as $role) {
            $spatieRole = SpatieRole::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
            $spatieRole->syncPermissions($role->permissionValues());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Remove accountant role
        SpatieRole::where('name', 'accountant')->where('guard_name', 'web')->delete();

        // Remove new permission
        SpatiePermission::where('name', 'organization.view-audit-log')->where('guard_name', 'web')->delete();

        // Re-sync remaining roles to their original permissions (without the new ones)
        foreach (Role::cases() as $role) {
            if ($role === Role::Accountant) {
                continue;
            }
            $spatieRole = SpatieRole::where('name', $role->value)->where('guard_name', 'web')->first();
            $spatieRole?->syncPermissions($role->permissionValues());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
