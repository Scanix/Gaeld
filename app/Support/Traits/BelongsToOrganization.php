<?php

namespace App\Support\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (app()->bound('current_organization')) {
                $org = app('current_organization');
                $builder->where(
                    $builder->getModel()->getTable().'.organization_id',
                    $org->id,
                );
            }
        });

        static::creating(function ($model) {
            if (! $model->organization_id && app()->bound('current_organization')) {
                $model->organization_id = app('current_organization')->id;
            }
        });
    }
}
