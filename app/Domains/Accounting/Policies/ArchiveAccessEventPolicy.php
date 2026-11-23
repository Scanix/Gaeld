<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class ArchiveAccessEventPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user) && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, ArchiveAccessEvent $event): bool
    {
        return $this->belongsToOrganization($user, $event) && $user->hasPermissionTo(Permission::AccountingView);
    }
}
