<?php

namespace Tests\Feature\Migration;

use App\Domains\Migration\Contracts\MigrationConnectorInterface;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Importers\ContactImporter;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Migration\Services\MigrationRegistry;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Providers\PluginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Plugins\ExamplePlugin\Migration\ExamplePluginContactImporter;
use Tests\TestCase;

class PluginMigrationManifestTest extends TestCase
{
    use RefreshDatabase;

    private string $pluginRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginRoot = storage_path('framework/testing/example-plugin');
        File::deleteDirectory($this->pluginRoot);
        File::copyDirectory(base_path('plugins/example-plugin'), $this->pluginRoot.'/example-plugin');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->pluginRoot);

        parent::tearDown();
    }

    public function test_manifest_declared_migration_parser_is_registered_when_plugin_is_loaded(): void
    {
        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $registry = $this->app->make(MigrationRegistry::class);
        $parser = $registry->getParser('example_app');

        $this->assertNotNull($parser);
        $this->assertInstanceOf(
            ExamplePluginContactImporter::class,
            $registry->getImporter(DataType::Contacts, 'example_app'),
        );
        $platform = collect($registry->availablePlatforms())
            ->firstWhere('platform', 'example_app');

        $this->assertSame('example-plugin', $platform['plugin']);
        $this->assertSame(
            'Example App',
            trans('app.migration.platform_example_app', [], 'en'),
        );
    }

    public function test_plugin_source_key_survives_a_session_and_parses_into_core_rows(): void
    {
        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $user = User::factory()->create();
        $organization = Organization::create([
            'name' => 'Plugin Import Test',
            'currency' => 'CHF',
        ]);
        $session = $this->app->make(MigrationOrchestrator::class)->startSession(
            $organization,
            'example_app',
            $user->id,
        );

        $file = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "name,email\nExample AG,hello@example.test\n",
        );
        $result = $this->app->make(MigrationOrchestrator::class)->parseFile(
            $session,
            $file,
            DataType::Contacts,
        );

        $this->assertSame('example_app', $session->platform);
        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Example AG', $result->rows->first()->name);
    }

    public function test_malformed_migration_extension_metadata_is_rejected(): void
    {
        $manifestPath = $this->pluginRoot.'/example-plugin/plugin.json';
        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['extensions']['migration']['parsers'] = 'not-a-list';
        File::put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertNull($this->app->make(MigrationRegistry::class)->getParser('example_app'));
    }

    public function test_generic_plugin_importer_is_not_registered_as_a_global_importer(): void
    {
        $manifestPath = $this->pluginRoot.'/example-plugin/plugin.json';
        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['extensions']['migration']['importers'] = [
            'Plugins\\ExamplePlugin\\Migration\\ExamplePluginParser',
        ];
        File::put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertInstanceOf(
            ContactImporter::class,
            $this->app->make(MigrationRegistry::class)->getImporter(DataType::Contacts, 'example_app'),
        );
    }

    public function test_plugin_cannot_register_an_extension_from_another_plugin_namespace(): void
    {
        $manifestPath = $this->pluginRoot.'/example-plugin/plugin.json';
        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['extensions']['migration']['parsers'] = [
            'Plugins\\GaeldEE\\Migration\\PrivateParser',
        ];
        File::put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

        config()->set('plugins.enabled', true);
        config()->set('plugins.path', $this->pluginRoot);

        $provider = new PluginServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertNull($this->app->make(MigrationRegistry::class)->getParser('example_app'));
    }

    public function test_connector_fetch_hides_provider_errors_from_the_import_flow(): void
    {
        $connector = $this->createMock(MigrationConnectorInterface::class);
        $connector->method('key')->willReturn('remote-contacts');
        $connector->method('platform')->willReturn('remote_accounting_app');
        $connector->method('supportedDataTypes')->willReturn([DataType::Contacts]);
        $connector->method('fetch')->willThrowException(new \RuntimeException('provider secret'));

        $registry = $this->app->make(MigrationRegistry::class);
        $registry->registerPluginConnector('community-connector', $connector);
        $session = new MigrationSession;
        $session->platform = 'remote_accounting_app';
        $result = $this->app->make(MigrationOrchestrator::class)->fetchFromConnector(
            $session,
            DataType::Contacts,
            new Organization,
        );

        $this->assertFalse($result->isSuccessful());
        $this->assertSame([__('migration.connector_fetch_failed')], $result->errors);
        $this->assertNotContains('provider secret', $result->errors);
    }
}
