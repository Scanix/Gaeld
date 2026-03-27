<?php

namespace App\Support\Contracts;

interface FeatureResolver
{
    /**
     * Determine if a feature is globally enabled.
     */
    public function enabled(string $feature): bool;

    /**
     * Determine if a feature is enabled for a specific organization.
     *
     * @param  string  $feature  Feature flag name
     * @param  mixed  $org  An Organization model instance
     */
    public function enabledForOrg(string $feature, mixed $org): bool;
}
