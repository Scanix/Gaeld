<?php

namespace App\Domains\Migration\Services;

use App\Domains\Migration\Contracts\AccountMapperInterface;
use App\Domains\Migration\Contracts\MigrationConnectorInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\Contracts\PluginDataTypeImporterInterface;

/**
 * Typed registration surface exposed to plugin service providers.
 */
final class PluginMigrationRegistrar
{
    public function __construct(
        private readonly MigrationRegistry $registry,
    ) {}

    public function registerParser(string $pluginSlug, PlatformParserInterface $parser): void
    {
        $this->registry->registerPluginParser($pluginSlug, $parser);
    }

    public function registerConnector(string $pluginSlug, MigrationConnectorInterface $connector): void
    {
        $this->registry->registerPluginConnector($pluginSlug, $connector);
    }

    public function registerImporter(string $pluginSlug, PluginDataTypeImporterInterface $importer): void
    {
        $this->registry->registerPluginImporter($pluginSlug, $importer);
    }

    public function registerMapper(string $pluginSlug, AccountMapperInterface $mapper): void
    {
        $this->registry->registerPluginMapper($pluginSlug, $mapper);
    }
}
