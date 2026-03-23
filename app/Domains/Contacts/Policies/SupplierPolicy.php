<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Contacts\Models\Supplier;
use App\Domains\Users\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->organizations()->where('organizations.id', $supplier->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->view($user, $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->view($user, $supplier);
    }
}
