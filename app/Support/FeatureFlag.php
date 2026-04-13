<?php

namespace App\Support;

use App\Support\Contracts\FeatureResolver;

/**
 * Feature flag facade: checks whether a feature (CE or EE) is enabled.
 *
 * Delegates to the container-bound FeatureResolver (ConfigFeatureResolver in CE,
 * SubscriptionFeatureResolver in EE). Use static calls for convenience.
 */
class FeatureFlag
{
    private static function resolver(): FeatureResolver
    {
        return app(FeatureResolver::class);
    }

    public static function enabled(string $feature): bool
    {
        return self::resolver()->enabled($feature);
    }

    public static function disabled(string $feature): bool
    {
        return ! static::enabled($feature);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return config('features', []);
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
     * The EE plugin overrides via SubscriptionFeatureResolver.
     */
    public static function enabledForOrg(string $feature, mixed $org): bool
    {
        return self::resolver()->enabledForOrg($feature, $org);
    }
}
