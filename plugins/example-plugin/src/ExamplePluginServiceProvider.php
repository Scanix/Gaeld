<?php

namespace Plugins\ExamplePlugin;

use Illuminate\Support\ServiceProvider;

class ExamplePluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang');

        // Load plugin routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load plugin migrations
        $this->loadMigrationsFrom(__DIR__.'/../migrations');

        // Load plugin views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'example-plugin');
    }
}
