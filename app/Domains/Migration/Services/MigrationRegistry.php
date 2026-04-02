<?php

namespace App\Domains\Migration\Services;

use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Migration\Contracts\AccountMapperInterface;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;

/**
 * Registry of platform parsers, data type importers, and account mappers.
 *
 * Follows the same pattern as {@see ChartTemplateService}:
 * implementations are registered at boot time and resolved by key.
 *
 * To add a new platform, register a new parser — the wizard auto-discovers
 * available platforms via {@see availablePlatforms()}.
 */
class MigrationRegistry
{
    /** @var array<string, PlatformParserInterface> */
    private array $parsers = [];

    /** @var array<string, DataTypeImporterInterface> */
    private array $importers = [];

    /** @var AccountMapperInterface[] */
    private array $mappers = [];

    // ──────────────────────────────────────────────────────────────
    //  Registration
    // ──────────────────────────────────────────────────────────────

    public function registerParser(PlatformParserInterface $parser): void
    {
        $this->parsers[$parser->platform()->value] = $parser;
    }

    public function registerImporter(DataTypeImporterInterface $importer): void
    {
        $this->importers[$importer->dataType()->value] = $importer;
    }

    public function registerMapper(AccountMapperInterface $mapper): void
    {
        $this->mappers[] = $mapper;
    }

    // ──────────────────────────────────────────────────────────────
    //  Lookups
    // ──────────────────────────────────────────────────────────────

    public function getParser(Platform $platform): ?PlatformParserInterface
    {
        return $this->parsers[$platform->value] ?? null;
    }

    public function getImporter(DataType $dataType): ?DataTypeImporterInterface
    {
        return $this->importers[$dataType->value] ?? null;
    }

    /**
     * @return AccountMapperInterface[]
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    // ──────────────────────────────────────────────────────────────
    //  Discovery
    // ──────────────────────────────────────────────────────────────

    /**
     * List all registered parsers for the UI.
     *
     * @return array<int, array{platform: string, label_key: string, description_key: string, data_types: string[], extensions: string[]}>
     */
    public function availablePlatforms(): array
    {
        return collect($this->parsers)->map(fn (PlatformParserInterface $p) => [
            'platform' => $p->platform()->value,
            'label_key' => $p->labelKey(),
            'description_key' => $p->descriptionKey(),
            'data_types' => array_map(fn (DataType $dt) => $dt->value, $p->supportedDataTypes()),
            'extensions' => $p->acceptedExtensions(),
        ])->values()->all();
    }

    /**
     * List all registered importers.
     *
     * @return array<string, DataTypeImporterInterface>
     */
    public function availableImporters(): array
    {
        return $this->importers;
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
    public function resolveImportOrder(array $requestedTypes): array
    {
        $requested = collect($requestedTypes)->keyBy(fn (DataType $dt) => $dt->value);
        $resolved = [];
        $resolving = [];

        $resolve = function (DataType $type) use (&$resolve, &$resolved, &$resolving, $requested): void {
            $key = $type->value;

            if (isset($resolved[$key])) {
                return;
            }

            if (isset($resolving[$key])) {
                return; // Break circular dependency
            }

            $resolving[$key] = true;
            $importer = $this->importers[$key] ?? null;

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
}
