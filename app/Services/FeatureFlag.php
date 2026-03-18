<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class FeatureFlag
{
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
}
