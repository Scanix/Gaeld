<?php

namespace App\Support\Traits;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait that automatically scopes queries to the current organization
 * via a global scope applied in the model's `booted()` method.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            $currentOrg = app(CurrentOrganization::class);

            // After EnsureHasOrganization middleware runs, use the bound service.
            // During route model binding (SubstituteBindings runs before route
            // middleware), fall back to the session value so that cross-org IDs
            // resolve to 404 rather than leaking their existence via a 403.
            if ($currentOrg->isBound()) {
                $orgId = $currentOrg->id();
            } else {
                $orgId = session('current_organization_id');
            }

            if ($orgId) {
                $builder->where(
                    $builder->getModel()->getTable().'.organization_id',
                    $orgId,
                );
            }
        });

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $currentOrg = app(CurrentOrganization::class);
                if ($currentOrg->isBound()) {
                    $model->organization_id = $currentOrg->id();
                }
            }
        });
    }
}
