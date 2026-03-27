<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    private array $loadedPlugins = [];

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

        // First pass: collect all manifests
        $manifests = [];
        foreach ($pluginDirs as $dir) {
            $manifest = $this->readManifest($dir);
            if ($manifest) {
                $manifests[$manifest['slug']] = ['dir' => $dir, 'manifest' => $manifest];
            }
        }

        // Second pass: load in dependency order
        foreach ($manifests as $slug => $entry) {
            $this->loadPluginWithDeps($slug, $manifests, []);
        }
    }

    public function boot(): void
    {
        //
    }

    private function readManifest(string $pluginDir): ?array
    {
        $manifestPath = $pluginDir . '/plugin.json';

        if (! File::exists($manifestPath)) {
            Log::warning("Plugin manifest not found: {$pluginDir}");

            return null;
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! $manifest || empty($manifest['provider']) || empty($manifest['slug'])) {
            Log::warning("Plugin manifest invalid or missing provider/slug: {$pluginDir}");

            return null;
        }

        if (isset($manifest['enabled']) && ! $manifest['enabled']) {
            return null;
        }

        return $manifest;
    }

    private function loadPluginWithDeps(string $slug, array $manifests, array $loading): void
    {
        if (isset($this->loadedPlugins[$slug])) {
            return;
        }

        if (in_array($slug, $loading, true)) {
            Log::warning("Circular plugin dependency detected: {$slug}");

            return;
        }

        if (! isset($manifests[$slug])) {
            return;
        }

        $entry = $manifests[$slug];
        $manifest = $entry['manifest'];
        $requires = $manifest['requires'] ?? [];

        // Load dependencies first
        $loading[] = $slug;
        foreach ($requires as $dep) {
            if (! isset($manifests[$dep])) {
                Log::warning("Plugin '{$slug}' requires '{$dep}' which is not installed");

                return;
            }
            $this->loadPluginWithDeps($dep, $manifests, $loading);
            if (! isset($this->loadedPlugins[$dep])) {
                Log::warning("Plugin '{$slug}' dependency '{$dep}' failed to load");

                return;
            }
        }

        $this->loadPlugin($entry['dir'], $manifest);
        $this->loadedPlugins[$slug] = $manifest['version'] ?? '0.0.0';
    }

    private function loadPlugin(string $pluginDir, array $manifest): void
    {
        $providerClass = $manifest['provider'];

        if (! str_starts_with($providerClass, 'Plugins\\')) {
            Log::warning("Plugin provider must be in Plugins\\ namespace, got: {$providerClass}");

            return;
        }

        // Load the plugin's own Composer dependencies if they were installed
        // separately (i.e. `composer install` was run inside the plugin directory).
        $pluginAutoload = $pluginDir . '/vendor/autoload.php';
        if (File::exists($pluginAutoload)) {
            require_once $pluginAutoload;
        }

        // Dynamically register a PSR-4 autoloader for this plugin's src/ directory.
        // This decouples directory naming (e.g. kebab-case slugs) from PHP namespaces
        // and allows plugins distributed as Composer packages OR dropped in manually.
        $srcPath = $pluginDir . '/src/';
        if (is_dir($srcPath)) {
            $parts = explode('\\', $providerClass);
            // Build namespace root from the first two segments: "Plugins\PluginName\"
            $namespaceRoot = implode('\\', array_slice($parts, 0, 2)) . '\\';
            spl_autoload_register(function (string $class) use ($namespaceRoot, $srcPath): void {
                if (str_starts_with($class, $namespaceRoot)) {
                    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($namespaceRoot)));
                    $file = $srcPath . $relative . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
        }

        if (class_exists($providerClass)) {
            $this->app->register($providerClass);
        } else {
            Log::warning("Plugin provider class not found: {$providerClass}");
        }
    }
}
