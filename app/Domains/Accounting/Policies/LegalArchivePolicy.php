<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for legal archive access.
 */
class LegalArchivePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, LegalArchive $legalArchive): bool
    {
        return $this->belongsToOrganization($user, $legalArchive)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }
}
