<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for cost centre management.
 */
class CostCenterPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $this->belongsToOrganization($user, $costCenter)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $this->belongsToOrganization($user, $costCenter)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $this->belongsToOrganization($user, $costCenter)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
