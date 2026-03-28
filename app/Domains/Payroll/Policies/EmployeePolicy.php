<?php

namespace App\Domains\Payroll\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class EmployeePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::PayrollView);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->belongsToOrganization($user, $employee)
            && $user->hasPermissionTo(Permission::PayrollView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::PayrollCreate);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->belongsToOrganization($user, $employee)
            && $user->hasPermissionTo(Permission::PayrollEdit);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->belongsToOrganization($user, $employee)
            && $user->hasPermissionTo(Permission::PayrollDelete);
    }
}
