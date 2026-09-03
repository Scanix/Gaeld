<?php

namespace Plugins\ExamplePlugin\Migration;

use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\Contracts\PlatformParserInterface;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\Enums\DataType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Example source parser showing how a plugin normalizes external data for CE.
 */
final class ExamplePluginParser implements PlatformParserInterface
{
    public function platform(): string
    {
        return 'example_app';
    }

    public function labelKey(): string
    {
        return 'migration.platform_example_app';
    }

    public function descriptionKey(): string
    {
        return 'migration.platform_example_app_desc';
    }

    /**
     * @return DataType[]
     */
    public function supportedDataTypes(): array
    {
        return [DataType::Contacts];
    }

    /**
     * @return string[]
     */
    public function acceptedExtensions(): array
    {
        return ['csv'];
    }

    /**
     * @return Collection<int, ImportRowInterface>
     */
    public function parse(UploadedFile $file, DataType $dataType): Collection
    {
        if ($dataType !== DataType::Contacts || $file->getRealPath() === false) {
            return collect();
        }

        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $csv->rewind();
        $headers = $csv->current();

        if (! is_array($headers)) {
            return collect();
        }

        $headers = array_map(
            fn (mixed $header): string => Str::snake(trim((string) $header)),
            $headers,
        );
        $rows = collect();
        $sourceRow = 2;
        $csv->next();

        while (! $csv->eof()) {
            $values = $csv->current();
            $csv->next();

            if (! is_array($values) || count($values) === 1 && $values[0] === null) {
                continue;
            }

            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));

            if ($row === false) {
                $sourceRow++;

                continue;
            }

            $contact = new ContactImportRow(
                sourceRow: $sourceRow,
                type: $this->value($row, 'type') ?? 'customer',
                name: $this->value($row, 'name') ?? '',
                email: $this->value($row, 'email'),
                phone: $this->value($row, 'phone'),
                address: $this->value($row, 'address'),
                zip: $this->value($row, 'zip'),
                city: $this->value($row, 'city'),
                country: $this->value($row, 'country'),
                vatNumber: $this->value($row, 'vat_number'),
                reference: $this->value($row, 'reference'),
                notes: $this->value($row, 'notes'),
            );

            if ($contact->name === '') {
                $contact->markInvalid();
            }

            $rows->push($contact);
            $sourceRow++;
        }

        return $rows;
    }

    public function detectDataType(UploadedFile $file): ?DataType
    {
        return DataType::Contacts;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function value(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }
}
