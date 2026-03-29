<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for VAT rate configuration.
 */
class VatRatePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, VatRate $vatRate): bool
    {
        return $this->belongsToOrganization($user, $vatRate)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, VatRate $vatRate): bool
    {
        return $this->belongsToOrganization($user, $vatRate)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, VatRate $vatRate): bool
    {
        return $this->belongsToOrganization($user, $vatRate)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
