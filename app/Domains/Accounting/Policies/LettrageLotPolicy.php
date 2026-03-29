<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\LettrageLot;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for lettrage (account reconciliation) lot management.
 */
class LettrageLotPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, LettrageLot $lettrageLot): bool
    {
        return $this->belongsToOrganization($user, $lettrageLot)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function delete(User $user, LettrageLot $lettrageLot): bool
    {
        return $this->belongsToOrganization($user, $lettrageLot)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
