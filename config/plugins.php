<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plugin System Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('PLUGINS_ENABLED', true),

    'path' => base_path('plugins'),

    'namespace' => 'Plugins',

];
