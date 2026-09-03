<?php

namespace App\Providers;

use App\Domains\Migration\Contracts\AccountMapperInterface;
use App\Domains\Migration\Contracts\MigrationConnectorInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\Contracts\PluginDataTypeImporterInterface;
use App\Domains\Migration\Services\PluginMigrationRegistrar;
use App\Support\Contracts\EditionCompatibility;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers and loads third-party plugins from the plugins directory at boot time.
 */
class PluginServiceProvider extends ServiceProvider
{
    /** @var array<string, bool> */
    private array $loadedPlugins = [];

    /** @var array<string, array<string, mixed>> */
    private array $manifests = [];

    public function register(): void
    {
        if (! config('plugins.enabled')) {
            return;
        }

        $pluginPath = config('plugins.path', base_path('plugins'));

        if (! File::isDirectory($pluginPath)) {
            return;
        }

        foreach (File::directories($pluginPath) as $dir) {
            $manifest = $this->readManifest($dir);
            if ($manifest) {
                $this->manifests[$manifest['slug']] = ['dir' => $dir, 'manifest' => $manifest];
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->manifests as $slug => $entry) {
            $this->loadPluginWithDeps($slug, $this->manifests, []);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $pluginDir): ?array
    {
        $manifestPath = $pluginDir.'/plugin.json';

        if (! File::exists($manifestPath)) {
            Log::warning("Plugin manifest not found: {$pluginDir}");

            return null;
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (
            ! is_array($manifest)
            || ! is_string($manifest['provider'] ?? null)
            || ! is_string($manifest['slug'] ?? null)
            || preg_match('/\A[a-z0-9][a-z0-9-]{0,63}\z/', $manifest['slug']) !== 1
            || ! str_starts_with($manifest['provider'], 'Plugins\\')
        ) {
            Log::warning("Plugin manifest invalid or missing provider/slug: {$pluginDir}");

            return null;
        }

        if (isset($manifest['enabled']) && ! $manifest['enabled']) {
            return null;
        }

        $compatibility = $manifest['compatibility'] ?? null;
        $requiresCompatibility = $manifest['slug'] === 'gaeld-ee' || $compatibility !== null;
        if ($requiresCompatibility && ! is_array($compatibility)) {
            Log::warning("Plugin manifest missing compatibility metadata: {$pluginDir}");

            return null;
        }

        if (is_array($compatibility)) {
            $reason = $this->app->make(EditionCompatibility::class)->incompatibilityReason($compatibility);
            if ($reason !== null) {
                Log::warning("Plugin manifest rejected ({$reason}): {$pluginDir}");

                return null;
            }
        }

        if (isset($manifest['extensions'])) {
            $extensions = $manifest['extensions'];
            $migrationExtensions = is_array($extensions)
                ? ($extensions['migration'] ?? [])
                : null;

            if (! is_array($extensions) || ! is_array($migrationExtensions)) {
                Log::warning("Plugin extensions metadata is invalid: {$pluginDir}");

                return null;
            }

            foreach (['parsers', 'connectors', 'importers', 'mappers'] as $extensionType) {
                if (isset($migrationExtensions[$extensionType]) && ! is_array($migrationExtensions[$extensionType])) {
                    Log::warning("Plugin migration extension metadata is invalid: {$pluginDir}");

                    return null;
                }
            }
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifests
     * @param  array<string, mixed>  $loading
     */
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

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function loadPlugin(string $pluginDir, array $manifest): void
    {
        $providerClass = $manifest['provider'];

        if (! str_starts_with($providerClass, 'Plugins\\')) {
            Log::warning("Plugin provider must be in Plugins\\ namespace, got: {$providerClass}");

            return;
        }

        // Load the plugin's own Composer dependencies if they were installed
        // separately (i.e. `composer install` was run inside the plugin directory).
        $pluginAutoload = $pluginDir.'/vendor/autoload.php';
        if (File::exists($pluginAutoload)) {
            require_once $pluginAutoload;
        }

        // Dynamically register a PSR-4 autoloader for this plugin's src/ directory.
        // This decouples directory naming (e.g. kebab-case slugs) from PHP namespaces
        // and allows plugins distributed as Composer packages OR dropped in manually.
        $srcPath = $pluginDir.'/src/';
        if (is_dir($srcPath)) {
            $parts = explode('\\', $providerClass);
            // Build namespace root from the first two segments: "Plugins\PluginName\"
            $namespaceRoot = implode('\\', array_slice($parts, 0, 2)).'\\';
            spl_autoload_register(function (string $class) use ($namespaceRoot, $srcPath): void {
                if (str_starts_with($class, $namespaceRoot)) {
                    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($namespaceRoot)));
                    $file = $srcPath.$relative.'.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
        }

        if (class_exists($providerClass)) {
            $this->app->register($providerClass);
            $this->registerMigrationExtensions($manifest);
        } else {
            Log::warning("Plugin provider class not found: {$providerClass}");
        }
    }

    /**
     * Register typed migration extensions declared by the plugin manifest.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function registerMigrationExtensions(array $manifest): void
    {
        $extensions = $manifest['extensions']['migration'] ?? [];

        if ($extensions === []) {
            return;
        }

        if (! is_array($extensions)) {
            Log::warning("Plugin migration extensions are invalid: {$manifest['slug']}");

            return;
        }

        $registrar = $this->app->make(PluginMigrationRegistrar::class);
        $namespaceRoot = $this->pluginNamespaceRoot($manifest['provider'] ?? null);

        if ($namespaceRoot === null) {
            Log::warning("Plugin migration extension provider namespace is invalid: {$manifest['slug']}");

            return;
        }

        foreach (['parsers', 'connectors', 'importers', 'mappers'] as $extensionType) {
            $classes = $extensions[$extensionType] ?? [];

            if (! is_array($classes)) {
                Log::warning("Plugin migration extension list is invalid: {$manifest['slug']}");

                continue;
            }

            foreach ($classes as $class) {
                if (! is_string($class) || ! str_starts_with($class, $namespaceRoot)) {
                    Log::warning("Plugin migration extension class is invalid: {$manifest['slug']}");

                    continue;
                }

                try {
                    $this->registerMigrationExtension(
                        $manifest['slug'],
                        $extensionType,
                        $class,
                        $registrar,
                    );
                } catch (\Throwable) {
                    Log::warning("Plugin migration extension could not be registered: {$manifest['slug']}");
                }
            }
        }
    }

    private function pluginNamespaceRoot(mixed $providerClass): ?string
    {
        if (! is_string($providerClass)) {
            return null;
        }

        $segments = explode('\\', trim($providerClass, '\\'));

        if (count($segments) < 3 || $segments[0] !== 'Plugins') {
            return null;
        }

        return implode('\\', array_slice($segments, 0, 2)).'\\';
    }

    private function registerMigrationExtension(
        string $pluginSlug,
        string $extensionType,
        string $class,
        PluginMigrationRegistrar $registrar,
    ): void {
        if (! class_exists($class)) {
            Log::warning("Plugin migration extension class not found: {$pluginSlug}");

            return;
        }

        $extension = $this->app->make($class);

        match ($extensionType) {
            'parsers' => $extension instanceof PlatformParserInterface
                ? $registrar->registerParser($pluginSlug, $extension)
                : Log::warning("Plugin migration parser has an invalid contract: {$pluginSlug}"),
            'connectors' => $extension instanceof MigrationConnectorInterface
                ? $registrar->registerConnector($pluginSlug, $extension)
                : Log::warning("Plugin migration connector has an invalid contract: {$pluginSlug}"),
            'importers' => $extension instanceof PluginDataTypeImporterInterface
                ? $registrar->registerImporter($pluginSlug, $extension)
                : Log::warning("Plugin migration importer has an invalid contract: {$pluginSlug}"),
            'mappers' => $extension instanceof AccountMapperInterface
                ? $registrar->registerMapper($pluginSlug, $extension)
                : Log::warning("Plugin migration mapper has an invalid contract: {$pluginSlug}"),
            default => null,
        };
    }
}
