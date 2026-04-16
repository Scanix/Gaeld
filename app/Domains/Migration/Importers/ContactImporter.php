<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactImporter implements DataTypeImporterInterface
{
    public function dataType(): DataType
    {
        return DataType::Contacts;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $errors = [];

        foreach ($rows as $row) {
            if (! $row instanceof ContactImportRow) {
                continue;
            }

            if (! $row->isValid()) {
                $errors[$row->sourceRow()] = $row->warnings();
            }

            if (empty($row->name)) {
                $errors[$row->sourceRow()] = ['Contact name is required'];
            }

            if (! in_array($row->type, ['customer', 'supplier'], true)) {
                $errors[$row->sourceRow()][] = "Invalid contact type: {$row->type}";
            }
        }

        if (! empty($errors)) {
            return ValidationResult::failure([], $errors);
        }

        return ValidationResult::success();
    }

    public function import(Collection $rows, Organization $organization): ImportResult
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $createdIds = [];

        foreach ($rows as $row) {
            if (! $row instanceof ContactImportRow || ! $row->isValid()) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $organization, &$imported, &$skipped, &$createdIds): void {
                    $data = [
                        'organization_id' => $organization->id,
                        'name' => $row->name,
                        'email' => $row->email,
                        'phone' => $row->phone,
                        'address' => $row->address,
                        'postal_code' => $row->zip,
                        'city' => $row->city,
                        'country' => self::normalizeCountry($row->country) ?? 'CH',
                    ];

                    $model = $row->type === 'supplier' ? Supplier::class : Customer::class;

                    // Deduplicate by name + email within same org
                    $existing = $model::where('organization_id', $organization->id)
                        ->where('name', $row->name)
                        ->when($row->email, fn ($q) => $q->where('email', $row->email))
                        ->first();

                    if ($existing) {
                        $skipped++;

                        return;
                    }

                    $record = $model::create($data);
                    $createdIds[] = $record->id;
                    $imported++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $rowNum = $row->sourceRow();
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::warning('Migration import: contact row failed', [
                    'row' => $rowNum,
                    'error' => $e->getMessage(),
                    'organization_id' => $organization->id,
                ]);
            }
        }

        if ($imported === 0 && $failed > 0) {
            return ImportResult::failure($this->dataType(), $errors, failed: $failed);
        }

        return ImportResult::success($this->dataType(), $imported, $skipped, warnings: $errors, createdIds: $createdIds, failed: $failed);
    }

    private static function normalizeCountry(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        // Already a 2-letter ISO code
        if (preg_match('/^[A-Z]{2}$/i', $value)) {
            return strtoupper($value);
        }

        // Common full names and alpha-3 codes from Swiss accounting exports
        $map = [
            // Alpha-3 → Alpha-2
            'che' => 'CH', 'deu' => 'DE', 'aut' => 'AT', 'fra' => 'FR',
            'ita' => 'IT', 'gbr' => 'GB', 'usa' => 'US', 'lie' => 'LI',

            // German
            'schweiz' => 'CH', 'deutschland' => 'DE', 'österreich' => 'AT',
            'frankreich' => 'FR', 'italien' => 'IT',

            // French
            'suisse' => 'CH', 'allemagne' => 'DE', 'autriche' => 'AT',
            'italie' => 'IT',

            // Italian
            'svizzera' => 'CH', 'germania' => 'DE', 'francia' => 'FR',
            'italia' => 'IT',

            // English (and shared names: france, austria, liechtenstein)
            'switzerland' => 'CH', 'germany' => 'DE', 'austria' => 'AT',
            'france' => 'FR', 'italy' => 'IT', 'liechtenstein' => 'LI',
            'united kingdom' => 'GB', 'united states' => 'US',
        ];

        return $map[mb_strtolower($value)] ?? mb_substr(strtoupper($value), 0, 2);
    }
}
