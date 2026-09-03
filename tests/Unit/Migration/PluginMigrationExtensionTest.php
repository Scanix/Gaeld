<?php

namespace Tests\Unit\Migration;

use App\Domains\Migration\Contracts\MigrationConnectorInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\Contracts\PluginDataTypeImporterInterface;
use App\Domains\Migration\DTOs\ParseResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Migration\Services\MigrationRegistry;
use App\Domains\Migration\Services\PluginMigrationRegistrar;
use App\Domains\Organizations\Models\Organization;
use PHPUnit\Framework\TestCase;

class PluginMigrationExtensionTest extends TestCase
{
    public function test_plugin_parser_can_register_a_string_source_key(): void
    {
        $registry = new MigrationRegistry;
        $parser = $this->createMock(PlatformParserInterface::class);
        $parser->method('platform')->willReturn('other_accounting_app');
        $parser->method('supportedDataTypes')->willReturn([DataType::Contacts]);
        $parser->method('labelKey')->willReturn('migration.platform_other_accounting_app');
        $parser->method('descriptionKey')->willReturn('migration.platform_other_accounting_app_desc');
        $parser->method('acceptedExtensions')->willReturn(['csv']);

        $registrar = new PluginMigrationRegistrar($registry);
        $registrar->registerParser('community-connector', $parser);

        $this->assertSame($parser, $registry->getParser('other_accounting_app'));
        $this->assertSame('community-connector', $registry->availablePlatforms()[0]['plugin']);
    }

    public function test_builtin_platform_keys_remain_valid_without_registered_parsers(): void
    {
        $registry = new MigrationRegistry;

        $this->assertSame(Platform::values(), $registry->availablePlatformKeys());
    }

    public function test_plugin_importer_is_selected_for_its_declared_source(): void
    {
        $registry = new MigrationRegistry;
        $importer = $this->createMock(PluginDataTypeImporterInterface::class);
        $importer->method('key')->willReturn('community-contacts');
        $importer->method('dataType')->willReturn(DataType::Contacts);
        $importer->method('supportedSources')->willReturn(['other_accounting_app']);

        $registrar = new PluginMigrationRegistrar($registry);
        $registrar->registerImporter('community-connector', $importer);

        $this->assertSame(
            $importer,
            $registry->getImporter(DataType::Contacts, 'other_accounting_app'),
        );
        $this->assertNull($registry->getImporter(DataType::Contacts, 'bexio'));
    }

    public function test_plugin_connector_registers_as_a_source_and_is_discoverable(): void
    {
        $registry = new MigrationRegistry;
        $connector = $this->createMock(MigrationConnectorInterface::class);
        $connector->method('key')->willReturn('remote-contacts');
        $connector->method('platform')->willReturn('remote_accounting_app');
        $connector->method('supportedDataTypes')->willReturn([DataType::Contacts]);
        $connector->method('fetch')->willReturn(new ParseResult(collect()));

        $registrar = new PluginMigrationRegistrar($registry);
        $registrar->registerConnector('community-connector', $connector);

        $this->assertSame($connector, $registry->getConnector('remote_accounting_app'));
        $this->assertSame('remote_accounting_app', $registry->availablePlatforms()[0]['platform']);
        $this->assertSame([], $registry->availablePlatforms()[0]['extensions']);
        $this->assertSame('connector', $registry->availablePlatforms()[0]['source_type']);
        $this->assertSame('community-connector', $registry->availableConnectors()[0]['plugin']);
    }

    public function test_plugin_importer_registration_does_not_partially_mutate_on_duplicate_source(): void
    {
        $registry = new MigrationRegistry;
        $importer = $this->createMock(PluginDataTypeImporterInterface::class);
        $importer->method('key')->willReturn('duplicate-source');
        $importer->method('dataType')->willReturn(DataType::Contacts);
        $importer->method('supportedSources')->willReturn(['first_source', 'first_source']);

        $registrar = new PluginMigrationRegistrar($registry);

        try {
            $registrar->registerImporter('community-connector', $importer);
            $this->fail('Expected duplicate source registration to fail.');
        } catch (\LogicException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertNull($registry->getImporter(DataType::Contacts, 'first_source'));
    }

    public function test_connector_fetch_is_organization_scoped_and_returns_normalized_result(): void
    {
        $registry = new MigrationRegistry;
        $connector = $this->createMock(MigrationConnectorInterface::class);
        $connector->method('key')->willReturn('remote-contacts');
        $connector->method('platform')->willReturn('remote_accounting_app');
        $connector->method('supportedDataTypes')->willReturn([DataType::Contacts]);
        $expected = new ParseResult(collect());
        $organization = new Organization;
        $connector->expects($this->once())
            ->method('fetch')
            ->with($organization, DataType::Contacts)
            ->willReturn($expected);

        $registry->registerPluginConnector('community-connector', $connector);
        $session = new MigrationSession;
        $session->platform = 'remote_accounting_app';

        $result = (new MigrationOrchestrator($registry))->fetchFromConnector(
            $session,
            DataType::Contacts,
            $organization,
        );

        $this->assertSame($expected, $result);
    }

    public function test_plugin_connector_cannot_claim_a_core_source(): void
    {
        $registry = new MigrationRegistry;
        $parser = $this->createMock(PlatformParserInterface::class);
        $parser->method('platform')->willReturn('shared_source');
        $parser->method('supportedDataTypes')->willReturn([DataType::Contacts]);
        $registry->registerParser($parser);

        $connector = $this->createMock(MigrationConnectorInterface::class);
        $connector->method('key')->willReturn('remote-contacts');
        $connector->method('platform')->willReturn('shared_source');

        $this->expectException(\LogicException::class);
        $registry->registerPluginConnector('community-connector', $connector);
    }
}
