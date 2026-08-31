<?php

namespace App\Domains\Organizations\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\FiscalYearChangeRequest;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class FiscalYearChangeRequestPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function view(User $user, FiscalYearChangeRequest $request): bool
    {
        return $this->belongsToOrganization($user, $request)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function update(User $user, FiscalYearChangeRequest $request): bool
    {
        return $this->belongsToOrganization($user, $request)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }

    public function approve(User $user, FiscalYearChangeRequest $request): bool
    {
        return $this->update($user, $request);
    }

    public function reject(User $user, FiscalYearChangeRequest $request): bool
    {
        return $this->update($user, $request);
    }

    public function apply(User $user, FiscalYearChangeRequest $request): bool
    {
        return $this->update($user, $request);
    }

    public function delete(User $user, FiscalYearChangeRequest $request): bool
    {
        return $this->belongsToOrganization($user, $request)
            && $user->hasPermissionTo(Permission::OrganizationEdit);
    }
}
