<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for fiscal year management.
 */
class FiscalYearPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->belongsToOrganization($user, $fiscalYear)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->belongsToOrganization($user, $fiscalYear)
            && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }

    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->belongsToOrganization($user, $fiscalYear)
            && $user->hasPermissionTo(Permission::AccountingCloseYear);
    }
}
