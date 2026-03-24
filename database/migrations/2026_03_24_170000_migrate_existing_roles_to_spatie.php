<?php

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed permissions and roles
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permission::values() as $permission) {
            SpatiePermission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (Role::cases() as $role) {
            $spatieRole = SpatieRole::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
            $spatieRole->syncPermissions($role->permissionValues());
        }

        // 2. Migrate existing organization_users pivot roles to spatie roles
        $pivotRows = DB::table('organization_users')->get();

        foreach ($pivotRows as $row) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($row->organization_id);

            $roleName = in_array($row->role, Role::values()) ? $row->role : Role::Member->value;
            $spatieRole = SpatieRole::findByName($roleName, 'web');

            // Assign role scoped to this organization
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $spatieRole->id,
                'model_type' => 'App\\Domains\\Users\\Models\\User',
                'model_id' => $row->user_id,
                'organization_id' => $row->organization_id,
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Roles and permissions are removed by dropping the permission tables migration
        DB::table('model_has_roles')
            ->where('model_type', 'App\\Domains\\Users\\Models\\User')
            ->delete();
    }
};
