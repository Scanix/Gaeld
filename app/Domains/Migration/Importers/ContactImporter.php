<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Models\Supplier;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\Contracts\ImportRowInterface;
use App\Domains\Migration\DTOs\ContactImportRow;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $createdIds = [];

        DB::transaction(function () use ($rows, $organization, &$imported, &$skipped, &$createdIds): void {
            foreach ($rows as $row) {
                if (! $row instanceof ContactImportRow || ! $row->isValid()) {
                    $skipped++;

                    continue;
                }

                $data = [
                    'organization_id' => $organization->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'address' => $row->address,
                    'postal_code' => $row->zip,
                    'city' => $row->city,
                    'country' => $row->country ?? 'CH',
                ];

                $model = $row->type === 'supplier' ? Supplier::class : Customer::class;

                // Deduplicate by name + email within same org
                $existing = $model::where('organization_id', $organization->id)
                    ->where('name', $row->name)
                    ->when($row->email, fn ($q) => $q->where('email', $row->email))
                    ->first();

                if ($existing) {
                    $skipped++;

                    continue;
                }

                $record = $model::create($data);
                $createdIds[] = $record->id;
                $imported++;
            }
        });

        return ImportResult::success($this->dataType(), $imported, $skipped, createdIds: $createdIds);
    }
}
