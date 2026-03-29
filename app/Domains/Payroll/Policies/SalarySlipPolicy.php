<?php

namespace App\Domains\Payroll\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for salary slip generation and access.
 */
class SalarySlipPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::PayrollView);
    }

    public function view(User $user, SalarySlip $salarySlip): bool
    {
        return $this->belongsToOrganization($user, $salarySlip)
            && $user->hasPermissionTo(Permission::PayrollView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::PayrollCreate);
    }
}
