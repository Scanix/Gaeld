<?php

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permission::values() as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (Role::cases() as $role) {
            SpatieRole::firstOrCreate(['name' => $role->value, 'guard_name' => 'web'])
                ->syncPermissions($role->permissionValues());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        SpatieRole::where('name', Role::Employee->value)->where('guard_name', 'web')->delete();
        SpatiePermission::whereIn('name', [
            Permission::ExpensesViewOwn->value,
            Permission::PayrollSalarySlipsViewOwn->value,
        ])->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
