<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class ArchivePackagePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user) && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, ArchivePackage $package): bool
    {
        return $this->belongsToOrganization($user, $package) && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user) && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }

    public function resume(User $user, ArchivePackage $package): bool
    {
        return $this->belongsToOrganization($user, $package) && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }
}
