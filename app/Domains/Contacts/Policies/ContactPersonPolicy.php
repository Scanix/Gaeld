<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Contacts\Models\ContactPerson;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for contact person management.
 */
class ContactPersonPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function view(User $user, ContactPerson $contactPerson): bool
    {
        return $this->belongsToOrganization($user, $contactPerson)
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ContactsCreate);
    }

    public function update(User $user, ContactPerson $contactPerson): bool
    {
        return $this->belongsToOrganization($user, $contactPerson)
            && $user->hasPermissionTo(Permission::ContactsEdit);
    }

    public function delete(User $user, ContactPerson $contactPerson): bool
    {
        return $this->belongsToOrganization($user, $contactPerson)
            && $user->hasPermissionTo(Permission::ContactsDelete);
    }
}
