<?php

namespace App\Domains\Migration\Contracts;

use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Enums\Platform;

/**
 * Shared metadata contract for file parsers and API-backed connectors.
 */
interface MigrationSourceInterface
{
    public function platform(): Platform|string;

    public function labelKey(): string;

    public function descriptionKey(): string;

    /**
     * @return DataType[]
     */
    public function supportedDataTypes(): array;
}
