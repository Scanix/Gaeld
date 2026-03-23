<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

class FeatureFlag
{
    /**
     * EE plugin registers a closure here via setOrgResolver() in its ServiceProvider.
     * CE leaves it null → enabledForOrg() falls back to the global flag.
     */
    private static ?\Closure $orgResolver = null;

    public static function setOrgResolver(\Closure $resolver): void
    {
        static::$orgResolver = $resolver;
    }

    public static function enabled(string $feature): bool
    {
        return (bool) Config::get("features.{$feature}", false);
    }

    public static function disabled(string $feature): bool
    {
        return ! static::enabled($feature);
    }

    public static function all(): array
    {
        return Config::get('features', []);
    }

    public static function isSaas(): bool
    {
        return static::enabled('saas');
    }

    public static function isCommunity(): bool
    {
        return ! static::isSaas();
    }

    /**
     * Check if a feature is enabled for a specific organization.
     * In CE, this defers to the global flag — all orgs are equal.
     * The EE plugin overrides this to enforce per-org plan limits.
     *
     * @param  string  $feature  Feature flag name (e.g. 'bank_sync')
     * @param  mixed   $org      An Organization model instance
     */
    public static function enabledForOrg(string $feature, mixed $org): bool
    {
        if (static::$orgResolver !== null) {
            return (static::$orgResolver)($feature, $org);
        }

        return static::enabled($feature);
    }
}
