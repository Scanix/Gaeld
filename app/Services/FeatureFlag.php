<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class FeatureFlag
{
    /**
     * Check if a feature is enabled.
     */
    public static function enabled(string $feature): bool
    {
        return (bool) Config::get("features.{$feature}", false);
    }

    /**
     * Check if a feature is disabled.
     */
    public static function disabled(string $feature): bool
    {
        return ! static::enabled($feature);
    }

    /**
     * Get all feature flags and their status.
     */
    public static function all(): array
    {
        return Config::get('features', []);
    }

    /**
     * Check if this is the SaaS edition.
     */
    public static function isSaas(): bool
    {
        return static::enabled('saas');
    }

    /**
     * Check if this is the Community edition.
     */
    public static function isCommunity(): bool
    {
        return ! static::isSaas();
    }
}
