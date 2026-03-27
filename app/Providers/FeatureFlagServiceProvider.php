<?php

namespace App\Providers;

use App\Http\Middleware\CheckFeatureFlag;
use App\Support\FeatureFlag;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class FeatureFlagServiceProvider extends ServiceProvider
{
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
