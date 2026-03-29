<?php

namespace App\Support\Policies;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract base policy that provides organization-scoped authorization helpers.
 */
abstract class BasePolicy
{
    protected function belongsToOrganization(User $user, Model $model): bool
    {
        return $user->organizations()->where('organizations.id', $model->organization_id)->exists();
    }

    protected function hasCurrentOrganization(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }
}
