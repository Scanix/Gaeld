<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for exchange rate management.
 */
class ExchangeRatePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->belongsToOrganization($user, $exchangeRate)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->belongsToOrganization($user, $exchangeRate)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->belongsToOrganization($user, $exchangeRate)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
