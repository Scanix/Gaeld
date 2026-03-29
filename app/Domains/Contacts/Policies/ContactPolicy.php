<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization policy for customer and supplier contact records.
 */
class ContactPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function view(User $user, Model $contact): bool
    {
        return $this->belongsToOrganization($user, $contact)
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::ContactsCreate);
    }

    public function update(User $user, Model $contact): bool
    {
        return $this->belongsToOrganization($user, $contact)
            && $user->hasPermissionTo(Permission::ContactsEdit);
    }

    public function delete(User $user, Model $contact): bool
    {
        return $this->belongsToOrganization($user, $contact)
            && $user->hasPermissionTo(Permission::ContactsDelete);
    }
}
