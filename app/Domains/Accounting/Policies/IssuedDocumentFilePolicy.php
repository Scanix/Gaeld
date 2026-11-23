<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

class IssuedDocumentFilePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user) && $user->hasPermissionTo(Permission::AccountingView);
    }

    public function view(User $user, IssuedDocumentFile $document): bool
    {
        return $this->belongsToOrganization($user, $document) && $user->hasPermissionTo(Permission::AccountingView);
    }
}
