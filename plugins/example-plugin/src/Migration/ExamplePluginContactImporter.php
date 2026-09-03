<?php

namespace Plugins\ExamplePlugin\Migration;

use App\Domains\Migration\Contracts\PluginDataTypeImporterInterface;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Importers\ContactImporter;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Example source-specific importer that delegates persistence to CE.
 */
final class ExamplePluginContactImporter implements PluginDataTypeImporterInterface
{
    public function __construct(
        private readonly ContactImporter $contacts,
    ) {}

    public function key(): string
    {
        return 'example-contacts';
    }

    /**
     * @return string[]
     */
    public function supportedSources(): array
    {
        return ['example_app'];
    }

    public function dataType(): DataType
    {
        return DataType::Contacts;
    }

    /**
     * @return DataType[]
     */
    public function dependencies(): array
    {
        return [];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        return $this->contacts->validate($rows, $organization);
    }

    public function import(Collection $rows, Organization $organization): ImportResult
    {
        return $this->contacts->import($rows, $organization);
    }
}
