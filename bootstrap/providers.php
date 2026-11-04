<?php

use App\Domains\Migration\Providers\MigrationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ArchiveServiceProvider;
use App\Providers\FeatureFlagServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\HttpsServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    MigrationServiceProvider::class,
    AppServiceProvider::class,
    ArchiveServiceProvider::class,
    FeatureFlagServiceProvider::class,
    HorizonServiceProvider::class,
    HttpsServiceProvider::class,
    PluginServiceProvider::class,
];
