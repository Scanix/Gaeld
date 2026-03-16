<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('plugins.enabled')) {
            return;
        }

        $pluginPath = config('plugins.path', base_path('plugins'));

        if (! File::isDirectory($pluginPath)) {
            return;
        }

        $pluginDirs = File::directories($pluginPath);

        foreach ($pluginDirs as $dir) {
            $this->loadPlugin($dir);
        }
    }

    public function boot(): void
    {
        //
    }

    private function loadPlugin(string $pluginDir): void
    {
        $manifestPath = $pluginDir . '/plugin.json';

        if (! File::exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! $manifest || empty($manifest['provider'])) {
            return;
        }

        if (isset($manifest['enabled']) && ! $manifest['enabled']) {
            return;
        }

        // Register the plugin's service provider
        $providerClass = $manifest['provider'];

        if (class_exists($providerClass)) {
            $this->app->register($providerClass);
        }
    }
}
