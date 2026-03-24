<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->organizations()->where('organizations.id', $customer->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::ContactsCreate);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->organizations()->where('organizations.id', $customer->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ContactsEdit);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->organizations()->where('organizations.id', $customer->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ContactsDelete);
    }
}
