<?php

namespace App\Support;

use App\Support\Contracts\FeatureResolver;
use Illuminate\Support\Facades\Config;

/**
 * CE default feature resolver: reads flags from config/features.php.
 * All organizations are treated equally in Community Edition.
 */
class ConfigFeatureResolver implements FeatureResolver
{
    public function enabled(string $feature): bool
    {
        return (bool) Config::get("features.{$feature}", false);
    }

    public function enabledForOrg(string $feature, mixed $org): bool
    {
        return $this->enabled($feature);
    }
}
