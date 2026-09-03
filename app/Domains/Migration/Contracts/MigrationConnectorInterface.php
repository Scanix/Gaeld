<?php

namespace App\Domains\Migration\Contracts;

use App\Domains\Migration\DTOs\ParseResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;

/**
 * Contract for a plugin that fetches migration data from an external API.
 *
 * The plugin owns authentication and credential storage. Core receives only
 * the organization context and normalized rows, never provider credentials.
 */
interface MigrationConnectorInterface extends MigrationSourceInterface
{
    /**
     * Stable identifier for this connector within the plugin.
     */
    public function key(): string;

    /**
     * Fetch and normalize data for one organization and target type.
     */
    public function fetch(Organization $organization, DataType $dataType): ParseResult;
}
