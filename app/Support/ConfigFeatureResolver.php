<?php

namespace App\Support;

use App\Domains\Organizations\Models\Organization;
use App\Support\Contracts\FeatureResolver;
use Illuminate\Support\Facades\Config;

/**
 * CE default feature resolver: reads flags from config/features.php.
 *
 * Per-org overrides: an Organization's $enabled_modules JSON map (key => bool)
 * takes precedence over the global config. Empty map = inherit defaults.
 */
class ConfigFeatureResolver implements FeatureResolver
{
    public function enabled(string $feature): bool
    {
        return (bool) Config::get("features.{$feature}", false);
    }

    public function enabledForOrg(string $feature, mixed $org): bool
    {
        if ($org instanceof Organization) {
            $modules = $org->enabled_modules;
            if (is_array($modules) && array_key_exists($feature, $modules)) {
                return (bool) $modules[$feature];
            }
        }

        return $this->enabled($feature);
    }
}
