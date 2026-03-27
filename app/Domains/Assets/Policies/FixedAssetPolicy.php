<?php

namespace App\Domains\Assets\Policies;

use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class FixedAssetPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, FixedAsset $asset): bool
    {
        return $this->belongsToOrganization($user, $asset)
            && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::AccountingCreate);
    }

    public function update(User $user, FixedAsset $asset): bool
    {
        return $this->belongsToOrganization($user, $asset)
            && $user->hasPermissionTo(Permission::AccountingEdit);
    }

    public function delete(User $user, FixedAsset $asset): bool
    {
        return $this->belongsToOrganization($user, $asset)
            && $user->hasPermissionTo(Permission::AccountingDelete);
    }
}
