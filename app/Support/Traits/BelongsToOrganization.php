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
            if ($currentOrg->isBound()) {
                $builder->where(
                    $builder->getModel()->getTable().'.organization_id',
                    $currentOrg->id(),
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
