<?php

namespace Tests\Unit\Migration;

use App\Domains\Migration\Contracts\AccountMapperInterface;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Services\MigrationRegistry;
use PHPUnit\Framework\TestCase;

class MigrationRegistryTest extends TestCase
{
    private MigrationRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new MigrationRegistry;
    }

    public function test_register_and_get_parser(): void
    {
        $parser = $this->createMockParser(Platform::Bexio, [DataType::Accounts]);
        $this->registry->registerParser($parser);

        $this->assertSame($parser, $this->registry->getParser(Platform::Bexio));
        $this->assertNull($this->registry->getParser(Platform::Banana));
    }

    public function test_register_and_get_importer(): void
    {
        $importer = $this->createMockImporter(DataType::Accounts);
        $this->registry->registerImporter($importer);

        $this->assertSame($importer, $this->registry->getImporter(DataType::Accounts));
        $this->assertNull($this->registry->getImporter(DataType::Contacts));
    }

    public function test_register_and_get_mappers(): void
    {
        $this->assertEmpty($this->registry->getMappers());

        $mapper = $this->createMock(AccountMapperInterface::class);
        $this->registry->registerMapper($mapper);

        $this->assertCount(1, $this->registry->getMappers());
    }

    public function test_available_platforms_returns_all_registered(): void
    {
        $parser1 = $this->createMockParser(Platform::Bexio, [DataType::Accounts, DataType::Contacts]);
        $parser2 = $this->createMockParser(Platform::Banana, [DataType::Accounts]);

        $this->registry->registerParser($parser1);
        $this->registry->registerParser($parser2);

        $platforms = $this->registry->availablePlatforms();

        $this->assertCount(2, $platforms);
        $this->assertSame('bexio', $platforms[0]['platform']);
        $this->assertSame('banana', $platforms[1]['platform']);
        $this->assertCount(2, $platforms[0]['data_types']);
    }

    public function test_resolve_import_order_respects_dependencies(): void
    {
        // Accounts have no deps
        $accounts = $this->createMockImporter(DataType::Accounts, []);
        // OpeningBalances depend on Accounts
        $balances = $this->createMockImporter(DataType::OpeningBalances, [DataType::Accounts]);
        // Contacts have no deps
        $contacts = $this->createMockImporter(DataType::Contacts, []);

        $this->registry->registerImporter($accounts);
        $this->registry->registerImporter($balances);
        $this->registry->registerImporter($contacts);

        $ordered = $this->registry->resolveImportOrder([
            DataType::OpeningBalances,
            DataType::Contacts,
            DataType::Accounts,
        ]);

        // Accounts must come before OpeningBalances
        $keys = array_map(fn (DataType $dt) => $dt->value, $ordered);
        $accountsPos = array_search('accounts', $keys);
        $balancesPos = array_search('opening_balances', $keys);

        $this->assertLessThan($balancesPos, $accountsPos, 'Accounts must be imported before OpeningBalances');
    }

    public function test_resolve_import_order_with_chain_dependencies(): void
    {
        // Accounts → OpeningBalances → YearEndClosing
        $accounts = $this->createMockImporter(DataType::Accounts, []);
        $balances = $this->createMockImporter(DataType::OpeningBalances, [DataType::Accounts]);
        $yearEnd = $this->createMockImporter(DataType::YearEndClosing, [DataType::Accounts, DataType::OpeningBalances]);

        $this->registry->registerImporter($accounts);
        $this->registry->registerImporter($balances);
        $this->registry->registerImporter($yearEnd);

        $ordered = $this->registry->resolveImportOrder([
            DataType::YearEndClosing,
            DataType::OpeningBalances,
            DataType::Accounts,
        ]);

        $keys = array_map(fn (DataType $dt) => $dt->value, $ordered);
        $this->assertSame('accounts', $keys[0]);
        $this->assertSame('opening_balances', $keys[1]);
        $this->assertSame('year_end_closing', $keys[2]);
    }

    public function test_resolve_import_order_ignores_unrequested_dependencies(): void
    {
        $accounts = $this->createMockImporter(DataType::Accounts, []);
        $balances = $this->createMockImporter(DataType::OpeningBalances, [DataType::Accounts]);

        $this->registry->registerImporter($accounts);
        $this->registry->registerImporter($balances);

        // Only request OpeningBalances — Accounts dependency is not in requested
        $ordered = $this->registry->resolveImportOrder([DataType::OpeningBalances]);

        $this->assertCount(1, $ordered);
        $this->assertSame(DataType::OpeningBalances, $ordered[0]);
    }

    private function createMockParser(Platform $platform, array $dataTypes): PlatformParserInterface
    {
        $parser = $this->createMock(PlatformParserInterface::class);
        $parser->method('platform')->willReturn($platform);
        $parser->method('supportedDataTypes')->willReturn($dataTypes);
        $parser->method('labelKey')->willReturn("migration.platform_{$platform->value}");
        $parser->method('descriptionKey')->willReturn("migration.platform_{$platform->value}_desc");
        $parser->method('acceptedExtensions')->willReturn(['csv']);

        return $parser;
    }

    private function createMockImporter(DataType $dataType, array $dependencies = []): DataTypeImporterInterface
    {
        $importer = $this->createMock(DataTypeImporterInterface::class);
        $importer->method('dataType')->willReturn($dataType);
        $importer->method('dependencies')->willReturn($dependencies);

        return $importer;
    }
}
