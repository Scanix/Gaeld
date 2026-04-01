<?php

namespace App\Domains\Migration\Contracts;

use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Contract for importing a specific data type into the system.
 *
 * Each implementation handles one {@see DataType} (accounts, contacts, etc.)
 * and follows a validate → import lifecycle. Implementations delegate
 * persistence to existing domain services (LedgerService, etc.).
 *
 * Adding a new importable data type requires only a new implementation
 * of this interface — no changes to the orchestration code.
 */
interface DataTypeImporterInterface
{
    /**
     * The data type this importer handles.
     */
    public function dataType(): DataType;

    /**
     * Data types that must be imported before this one.
     *
     * @return DataType[]
     */
    public function dependencies(): array;

    /**
     * Validate a collection of parsed rows before import.
     *
     * @param  Collection<int, ImportRowInterface>  $rows
     */
    public function validate(Collection $rows, Organization $organization): ValidationResult;

    /**
     * Execute the import for validated rows.
     *
     * @param  Collection<int, ImportRowInterface>  $rows
     */
    public function import(Collection $rows, Organization $organization): ImportResult;
}
