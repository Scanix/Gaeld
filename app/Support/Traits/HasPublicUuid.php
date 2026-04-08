<?php

namespace App\Support\Traits;

use Illuminate\Support\Str;

/**
 * Adds a `uuid` column for external/URL exposure while keeping
 * the integer `id` as the internal primary key for efficient JOINs.
 *
 * Auto-generates the UUID on model instantiation (supports saveQuietly)
 * and uses it for route model binding.
 */
trait HasPublicUuid
{
    public function initializeHasPublicUuid(): void
    {
        if (! $this->exists && empty($this->uuid)) {
            $this->uuid = (string) Str::uuid();
        }
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
