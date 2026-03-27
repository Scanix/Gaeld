<?php

namespace App\Support\Traits;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Adds organisation-scoped audit logging to a model.
 *
 * Uses Spatie Activity Log under the hood, automatically recording:
 *  - created / updated / deleted events
 *  - changed attributes (old → new)
 *  - the authenticated user (causer)
 *  - the organization_id via properties
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => class_basename($this)." {$event}");
    }

    public function tapActivity(Activity $activity): void
    {
        if (property_exists($this, 'organization_id') && $this->organization_id) {
            $activity->properties = $activity->properties->merge([
                'organization_id' => $this->organization_id,
            ]);
        }
    }
}
