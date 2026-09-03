<?php

namespace App\Domains\Migration\Services;

use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Migration\Contracts\AccountMapperInterface;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\Contracts\MigrationConnectorInterface;
use App\Domains\Migration\Contracts\MigrationSourceInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\Contracts\PluginDataTypeImporterInterface;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;
use InvalidArgumentException;
use LogicException;

/**
 * Registry of platform parsers, data type importers, and account mappers.
 *
 * Follows the same pattern as {@see ChartTemplateService}:
 * implementations are registered at boot time and resolved by key.
 * To add a new platform, register a new parser — the wizard auto-discovers
 * available platforms via {@see availablePlatforms()}.
 */
class MigrationRegistry
{
    /** @var array<string, PlatformParserInterface> */
    private array $parsers = [];

    /** @var array<string, MigrationSourceInterface> */
    private array $sources = [];

    /** @var array<string, string|null> */
    private array $sourceOwners = [];

    /** @var array<string, MigrationConnectorInterface> */
    private array $connectors = [];

    /** @var array<string, string|null> */
    private array $connectorOwners = [];

    /** @var array<string, DataTypeImporterInterface> */
    private array $importers = [];

    /** @var array<string, array<string, PluginDataTypeImporterInterface>> */
    private array $pluginImporters = [];

    /** @var array<string, PluginDataTypeImporterInterface> */
    private array $pluginImporterKeys = [];

    /** @var array<string, MigrationConnectorInterface> */
    private array $connectorKeys = [];

    /** @var AccountMapperInterface[] */
    private array $mappers = [];

    // ──────────────────────────────────────────────────────────────
    //  Registration
    // ──────────────────────────────────────────────────────────────

    public function registerParser(PlatformParserInterface $parser): void
    {
        $this->registerParserForOwner($parser, null);
    }

    public function registerPluginParser(string $pluginSlug, PlatformParserInterface $parser): void
    {
        $this->assertPluginSlug($pluginSlug);
        $this->registerParserForOwner($parser, $pluginSlug);
    }

    public function registerImporter(DataTypeImporterInterface $importer): void
    {
        $dataType = $importer->dataType()->value;

        if (isset($this->importers[$dataType])) {
            throw new LogicException("Migration importer already registered for [{$dataType}].");
        }

        $this->importers[$dataType] = $importer;
    }

    public function registerPluginImporter(string $pluginSlug, PluginDataTypeImporterInterface $importer): void
    {
        $this->assertPluginSlug($pluginSlug);

        $dataType = $importer->dataType()->value;
        $importerKey = $this->extensionKey($importer->key(), 'importer');
        $sources = $importer->supportedSources();

        if ($sources === []) {
            throw new InvalidArgumentException("Plugin importer [{$pluginSlug}:{$importerKey}] must declare at least one source.");
        }

        $sourceKeys = [];
        foreach ($sources as $source) {
            $sourceKey = $source === '*' ? '*' : $this->sourceKey($source);

            if (in_array($sourceKey, $sourceKeys, true) || isset($this->pluginImporters[$dataType][$sourceKey])) {
                throw new LogicException("Migration importer already registered for [{$dataType}] and source [{$sourceKey}].");
            }

            $sourceKeys[] = $sourceKey;
        }

        $registrationKey = $pluginSlug.':'.$importerKey;
        if (isset($this->pluginImporterKeys[$registrationKey])) {
            throw new LogicException("Migration importer key already registered: [{$registrationKey}].");
        }

        $this->pluginImporterKeys[$registrationKey] = $importer;
        foreach ($sourceKeys as $sourceKey) {
            $this->pluginImporters[$dataType][$sourceKey] = $importer;
        }
    }

    public function registerMapper(AccountMapperInterface $mapper): void
    {
        $this->mappers[] = $mapper;
    }

    public function registerPluginMapper(string $pluginSlug, AccountMapperInterface $mapper): void
    {
        $this->assertPluginSlug($pluginSlug);
        $this->registerMapper($mapper);
    }

    public function registerPluginConnector(string $pluginSlug, MigrationConnectorInterface $connector): void
    {
        $this->assertPluginSlug($pluginSlug);
        $connectorKey = $this->extensionKey($connector->key(), 'connector');
        $source = $this->sourceKey($connector->platform());

        if (isset($this->connectors[$source])) {
            throw new LogicException("Migration connector already registered for source [{$source}].");
        }

        if (isset($this->sources[$source]) && ($this->sourceOwners[$source] ?? null) !== $pluginSlug) {
            throw new LogicException("Migration source already belongs to another provider: [{$source}].");
        }

        $registrationKey = $pluginSlug.':'.$connectorKey;
        if (isset($this->connectorKeys[$registrationKey])) {
            throw new LogicException("Migration connector key already registered: [{$registrationKey}].");
        }

        if (! isset($this->sources[$source])) {
            $this->registerSourceForOwner($connector, $pluginSlug);
        }

        $this->connectors[$source] = $connector;
        $this->connectorOwners[$source] = $pluginSlug;
        $this->connectorKeys[$registrationKey] = $connector;
    }

    // ──────────────────────────────────────────────────────────────
    //  Lookups
    // ──────────────────────────────────────────────────────────────

    public function getParser(Platform|string $platform): ?PlatformParserInterface
    {
        return $this->parsers[$this->sourceKey($platform)] ?? null;
    }

    public function getConnector(Platform|string $platform): ?MigrationConnectorInterface
    {
        return $this->connectors[$this->sourceKey($platform)] ?? null;
    }

    public function getImporter(DataType $dataType, Platform|string|null $source = null): ?DataTypeImporterInterface
    {
        if ($source !== null) {
            $sourceKey = $this->sourceKey($source);

            return $this->pluginImporters[$dataType->value][$sourceKey]
                ?? $this->pluginImporters[$dataType->value]['*']
                ?? $this->importers[$dataType->value]
                ?? null;
        }

        return $this->importers[$dataType->value] ?? null;
    }

    /**
     * @return AccountMapperInterface[]
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    public function sourceKey(Platform|string $platform): string
    {
        $key = $platform instanceof Platform ? $platform->value : $platform;

        if (preg_match('/\A[a-z0-9][a-z0-9._-]{0,99}\z/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid migration source key [{$key}].");
        }

        return $key;
    }

    // ──────────────────────────────────────────────────────────────
    //  Discovery
    // ──────────────────────────────────────────────────────────────

    /**
     * List all registered file parsers and API connectors for the UI.
     *
     * @return array<int, array{platform: string, label_key: string, description_key: string, data_types: string[], extensions: string[], source_type: string, plugin: string|null}>
     */
    public function availablePlatforms(): array
    {
        return collect($this->sources)->map(function (MigrationSourceInterface $sourceDefinition, string $source): array {
            return [
                'platform' => $source,
                'label_key' => $sourceDefinition->labelKey(),
                'description_key' => $sourceDefinition->descriptionKey(),
                'data_types' => array_map(fn (DataType $dataType) => $dataType->value, $sourceDefinition->supportedDataTypes()),
                'extensions' => $sourceDefinition instanceof PlatformParserInterface
                    ? $sourceDefinition->acceptedExtensions()
                    : [],
                'source_type' => $sourceDefinition instanceof MigrationConnectorInterface ? 'connector' : 'file',
                'plugin' => $this->sourceOwners[$source] ?? null,
            ];
        })->values()->all();
    }

    /**
     * Return the UI metadata for one source key.
     *
     * @return array{platform: string, label_key: string, description_key: string, data_types: string[], extensions: string[], source_type: string, plugin: string|null}|null
     */
    public function platformMetadata(Platform|string $platform): ?array
    {
        $source = $this->sourceKey($platform);
        $sourceDefinition = $this->sources[$source] ?? null;

        if ($sourceDefinition === null) {
            return null;
        }

        return [
            'platform' => $source,
            'label_key' => $sourceDefinition->labelKey(),
            'description_key' => $sourceDefinition->descriptionKey(),
            'data_types' => array_map(fn (DataType $dataType) => $dataType->value, $sourceDefinition->supportedDataTypes()),
            'extensions' => $sourceDefinition instanceof PlatformParserInterface
                ? $sourceDefinition->acceptedExtensions()
                : [],
            'source_type' => $sourceDefinition instanceof MigrationConnectorInterface ? 'connector' : 'file',
            'plugin' => $this->sourceOwners[$source] ?? null,
        ];
    }

    /**
     * @return string[]
     */
    public function availablePlatformKeys(): array
    {
        return array_values(array_unique([
            ...Platform::values(),
            ...array_keys($this->sources),
        ]));
    }

    /**
     * @return array<int, array{key: string, platform: string, data_types: string[], plugin: string|null}>
     */
    public function availableConnectors(): array
    {
        return collect($this->connectors)->map(function (MigrationConnectorInterface $connector, string $source): array {
            return [
                'key' => $connector->key(),
                'platform' => $source,
                'data_types' => array_map(fn (DataType $dataType) => $dataType->value, $connector->supportedDataTypes()),
                'plugin' => $this->connectorOwners[$source] ?? null,
            ];
        })->values()->all();
    }

    /**
     * List all registered importers.
     *
     * @return array<string, DataTypeImporterInterface>
     */
    public function availableImporters(Platform|string|null $source = null): array
    {
        if ($source === null) {
            return $this->importers;
        }

        $sourceKey = $this->sourceKey($source);
        $importers = $this->importers;

        foreach ($this->pluginImporters as $dataType => $sourceImporters) {
            $pluginImporter = $sourceImporters[$sourceKey] ?? $sourceImporters['*'] ?? null;
            if ($pluginImporter !== null) {
                $importers[$dataType] = $pluginImporter;
            }
        }

        return $importers;
    }

    // ──────────────────────────────────────────────────────────────
    //  Dependency Resolution
    // ──────────────────────────────────────────────────────────────

    /**
     * Resolve the correct import order based on declared dependencies.
     *
     * @param  DataType[]  $requestedTypes
     * @return DataType[] Topologically sorted
     */
    public function resolveImportOrder(array $requestedTypes, Platform|string|null $source = null): array
    {
        $requested = collect($requestedTypes)->keyBy(fn (DataType $dt) => $dt->value);
        $resolved = [];
        $resolving = [];

        $resolve = function (DataType $type) use (&$resolve, &$resolved, &$resolving, $requested, $source): void {
            $key = $type->value;

            if (isset($resolved[$key])) {
                return;
            }

            if (isset($resolving[$key])) {
                return; // Break circular dependency
            }

            $resolving[$key] = true;
            $importer = $this->getImporter($type, $source);

            if ($importer) {
                foreach ($importer->dependencies() as $dep) {
                    if ($requested->has($dep->value)) {
                        $resolve($dep);
                    }
                }
            }

            unset($resolving[$key]);
            $resolved[$key] = $type;
        };

        foreach ($requestedTypes as $type) {
            $resolve($type);
        }

        return array_values($resolved);
    }

    private function registerParserForOwner(PlatformParserInterface $parser, ?string $pluginSlug): void
    {
        $source = $this->registerSourceForOwner($parser, $pluginSlug);
        $this->parsers[$source] = $parser;
    }

    private function registerSourceForOwner(MigrationSourceInterface $sourceDefinition, ?string $pluginSlug): string
    {
        $source = $this->sourceKey($sourceDefinition->platform());

        if (isset($this->sources[$source])) {
            throw new LogicException("Migration source already registered for [{$source}].");
        }

        $this->sources[$source] = $sourceDefinition;
        $this->sourceOwners[$source] = $pluginSlug;

        return $source;
    }

    private function assertPluginSlug(string $pluginSlug): void
    {
        if (preg_match('/\A[a-z0-9][a-z0-9-]{0,63}\z/', $pluginSlug) !== 1) {
            throw new InvalidArgumentException("Invalid plugin slug [{$pluginSlug}].");
        }
    }

    private function extensionKey(string $key, string $extensionType): string
    {
        if (preg_match('/\A[a-z0-9][a-z0-9._-]{0,99}\z/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid plugin {$extensionType} key [{$key}].");
        }

        return $key;
    }
}
