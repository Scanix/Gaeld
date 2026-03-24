<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Contacts\Models\Supplier;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->organizations()->where('organizations.id', $supplier->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ContactsView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::ContactsCreate);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->organizations()->where('organizations.id', $supplier->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ContactsEdit);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->organizations()->where('organizations.id', $supplier->organization_id)->exists()
            && $user->hasPermissionTo(Permission::ContactsDelete);
    }
}
