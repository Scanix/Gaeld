<?php

namespace App\Domains\Migration\Policies;

use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class MigrationSessionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::MigrationImport);
    }

    public function view(User $user, MigrationSession $session): bool
    {
        return $this->belongsToOrganization($user, $session)
            && $user->hasPermissionTo(Permission::MigrationImport);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::MigrationImport);
    }

    public function update(User $user, MigrationSession $session): bool
    {
        return $this->belongsToOrganization($user, $session)
            && $user->hasPermissionTo(Permission::MigrationImport);
    }

    public function delete(User $user, MigrationSession $session): bool
    {
        return $this->belongsToOrganization($user, $session)
            && $user->hasPermissionTo(Permission::MigrationImport);
    }
}
