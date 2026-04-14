<?php

namespace App\Support\Policies;

use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract base policy that provides organization-scoped authorization helpers.
 */
abstract class BasePolicy
{
    protected function belongsToOrganization(User $user, Model $model): bool
    {
        $currentOrg = app(CurrentOrganization::class);

        if ($currentOrg->isBound()) {
            return $model->organization_id === $currentOrg->id();
        }

        // Fallback for unit tests and service-layer calls without an HTTP context.
        return $user->organizations()->where('organizations.id', $model->organization_id)->exists();
    }

    protected function hasCurrentOrganization(User $user): bool
    {
        if (app(CurrentOrganization::class)->isBound()) {
            return true;
        }

        // Fallback for unit tests and service-layer calls without an HTTP context.
        return $user->organizations()->exists();
    }
}
