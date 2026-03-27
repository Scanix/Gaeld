<?php

namespace App\Console\Commands;

use App\Domains\Organizations\Enums\Role;
use App\Domains\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'gaeld:sync-permissions {--debug : Show current permission state}';

    protected $description = 'Assign spatie roles to organization members who are missing them';

    public function handle(): int
    {
        // Ensure roles exist
        $this->callSilently('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        if ($this->option('debug')) {
            return $this->debug();
        }

        $pivotRows = DB::table('organization_users')->get();
        $fixed = 0;
        $skipped = 0;

        foreach ($pivotRows as $row) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($row->organization_id);

            // Check if user already has a spatie role for this org
            $exists = DB::table('model_has_roles')
                ->where('model_type', 'App\\Domains\\Users\\Models\\User')
                ->where('model_id', $row->user_id)
                ->where('organization_id', $row->organization_id)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $roleName = in_array($row->role, Role::values()) ? $row->role : Role::Member->value;
            $spatieRole = SpatieRole::findByName($roleName, 'web');

            DB::table('model_has_roles')->insert([
                'role_id' => $spatieRole->id,
                'model_type' => 'App\\Domains\\Users\\Models\\User',
                'model_id' => $row->user_id,
                'organization_id' => $row->organization_id,
            ]);

            $fixed++;
            $this->line("  Assigned <info>{$roleName}</info> to user #{$row->user_id} in org {$row->organization_id}");
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info("Done. Fixed: {$fixed}, already assigned: {$skipped}.");

        return self::SUCCESS;
    }

    private function debug(): int
    {
        $roles = SpatieRole::with('permissions')->get();
        $this->info("Roles: {$roles->count()}");
        foreach ($roles as $r) {
            $this->line("  {$r->name} => {$r->permissions->count()} permissions");
        }

        $permCount = Permission::count();
        $this->info("Total permissions: {$permCount}");

        $userRoles = DB::table('model_has_roles')->get();
        $this->info("User-role assignments: {$userRoles->count()}");
        foreach ($userRoles as $ur) {
            $roleName = $roles->firstWhere('id', $ur->role_id)?->name ?? '?';
            $this->line("  user={$ur->model_id} role={$roleName} org={$ur->organization_id}");
        }

        // Test hasPermissionTo for the first user
        $first = $userRoles->first();
        if ($first) {
            app()[PermissionRegistrar::class]->setPermissionsTeamId($first->organization_id);
            $user = User::find($first->model_id);
            if ($user) {
                $this->info("Testing user {$user->email}:");
                $this->line('  Roles: '.$user->getRoleNames()->implode(', '));
                $this->line('  Permissions: '.$user->getAllPermissions()->pluck('name')->implode(', '));
                $this->line('  hasPermissionTo(accounting.view): '.($user->hasPermissionTo('accounting.view') ? 'YES' : 'NO'));
            }
        }

        return self::SUCCESS;
    }
}
