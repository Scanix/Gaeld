<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\TaxDeclaration;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for tax declaration management.
 */
class TaxDeclarationPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, TaxDeclaration $taxDeclaration): bool
    {
        return $this->belongsToOrganization($user, $taxDeclaration)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, TaxDeclaration $taxDeclaration): bool
    {
        return $this->belongsToOrganization($user, $taxDeclaration)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, TaxDeclaration $taxDeclaration): bool
    {
        return $this->belongsToOrganization($user, $taxDeclaration)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
