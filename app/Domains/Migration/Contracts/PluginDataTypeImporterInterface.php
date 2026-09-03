<?php

namespace App\Domains\Migration\Contracts;

/**
 * Contract for a plugin importer that replaces one normalized target type
 * for one or more external migration sources.
 */
interface PluginDataTypeImporterInterface extends DataTypeImporterInterface
{
    /**
     * Stable identifier for this importer within the plugin.
     */
    public function key(): string;

    /**
     * Source keys handled by this importer. Use '*' to handle every source.
     *
     * @return string[]
     */
    public function supportedSources(): array;
}
