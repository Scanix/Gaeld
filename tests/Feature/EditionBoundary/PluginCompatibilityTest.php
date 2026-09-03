<?php

namespace Tests\Feature\EditionBoundary;

use App\Providers\PluginServiceProvider;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginCompatibilityTest extends TestCase
{
    private string $pluginRoot;

    private string $pluginPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginRoot = storage_path('framework/testing/plugins');
        $this->pluginPath = $this->pluginRoot.'/incompatible-plugin';
        File::deleteDirectory($this->pluginRoot);
        File::makeDirectory($this->pluginPath.'/src', 0755, true);
        File::put($this->pluginPath.'/src/TestServiceProvider.php', <<<'PHP'
<?php

namespace Plugins\TestPlugin;

use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('test.incompatible.plugin', fn (): bool => true);
    }
}
PHP);
        File::put($this->pluginPath.'/plugin.json', json_encode([
            'name' => 'Incompatible plugin',
            'slug' => 'incompatible-plugin',
            'version' => '1.0.0',
            'provider' => 'Plugins\\TestPlugin\\TestServiceProvider',
            'compatibility' => [
                'contract_version' => '9.0.0',
                'ee_version' => '2.9.18',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginRoot);

        parent::tearDown();
    }

    public function test_incompatible_plugin_is_not_registered(): void
    {
        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertFalse($this->app->bound('test.incompatible.plugin'));
    }

    public function test_non_commercial_plugin_without_edition_metadata_remains_supported(): void
    {
        File::put($this->pluginPath.'/plugin.json', json_encode([
            'name' => 'Community plugin',
            'slug' => 'community-plugin',
            'version' => '1.0.0',
            'provider' => 'Plugins\\TestPlugin\\TestServiceProvider',
        ], JSON_THROW_ON_ERROR));

        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue($this->app->bound('test.incompatible.plugin'));
    }
}
