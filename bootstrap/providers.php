<?php

use App\Domains\Migration\Providers\MigrationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FeatureFlagServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    MigrationServiceProvider::class,
    AppServiceProvider::class,
    FeatureFlagServiceProvider::class,
    HorizonServiceProvider::class,
    PluginServiceProvider::class,
];
