<?php

namespace App\Providers;

use App\Http\Middleware\CheckFeatureFlag;
use App\Support\ConfigFeatureResolver;
use App\Support\Contracts\FeatureResolver;
use App\Support\FeatureFlag;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Registers feature flag middleware and Blade directives for conditional feature toggling.
 */
class FeatureFlagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureResolver::class, ConfigFeatureResolver::class);
    }

    public function boot(): void
    {
        // Register middleware alias
        $this->app['router']->aliasMiddleware('feature', CheckFeatureFlag::class);

        // Blade directives for feature flags
        Blade::if('feature', function (string $feature) {
            return FeatureFlag::enabled($feature);
        });
    }
}
