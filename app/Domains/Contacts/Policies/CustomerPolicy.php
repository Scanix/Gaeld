<?php

namespace App\Domains\Contacts\Policies;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Users\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->organizations()->where('organizations.id', $customer->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
