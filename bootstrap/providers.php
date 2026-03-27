<?php

use App\Providers\AppServiceProvider;
use App\Providers\FeatureFlagServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    AppServiceProvider::class,
    FeatureFlagServiceProvider::class,
    PluginServiceProvider::class,
];
