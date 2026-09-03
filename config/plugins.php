<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plugin System Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('PLUGINS_ENABLED', false),

    'path' => base_path('plugins'),

    'namespace' => 'Plugins',

];
