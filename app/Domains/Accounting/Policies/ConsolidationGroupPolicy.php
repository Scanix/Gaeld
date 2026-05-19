<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for consolidation group management.
 */
class ConsolidationGroupPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, ConsolidationGroup $consolidationGroup): bool
    {
        return $this->belongsToOrganization($user, $consolidationGroup)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, ConsolidationGroup $consolidationGroup): bool
    {
        return $this->belongsToOrganization($user, $consolidationGroup)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, ConsolidationGroup $consolidationGroup): bool
    {
        return $this->belongsToOrganization($user, $consolidationGroup)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
