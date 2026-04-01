<?php

use App\Domains\Migration\Providers\MigrationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FeatureFlagServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    AppServiceProvider::class,
    FeatureFlagServiceProvider::class,
    PluginServiceProvider::class,
    MigrationServiceProvider::class,
];
