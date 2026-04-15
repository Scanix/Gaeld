<?php

namespace App\Domains\Migration\Importers;

use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Migration\Contracts\DataTypeImporterInterface;
use App\Domains\Migration\DTOs\FixedAssetImportRow;
use App\Domains\Migration\DTOs\ImportResult;
use App\Domains\Migration\DTOs\ValidationResult;
use App\Domains\Migration\Enums\DataType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixedAssetImporter implements DataTypeImporterInterface
{
    public function dataType(): DataType
    {
        return DataType::FixedAssets;
    }

    public function dependencies(): array
    {
        return [DataType::Accounts];
    }

    public function validate(Collection $rows, Organization $organization): ValidationResult
    {
        $errors = [];

        foreach ($rows as $row) {
            if (! $row instanceof FixedAssetImportRow || ! $row->isValid()) {
                continue;
            }

            if (empty($row->name)) {
                $errors[$row->sourceRow()][] = 'Asset name is required';
            }

            if (empty($row->acquisitionDate)) {
                $errors[$row->sourceRow()][] = 'Acquisition date is required';
            }

            if ((float) $row->acquisitionCost <= 0) {
                $errors[$row->sourceRow()][] = 'Acquisition cost must be positive';
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

        foreach ($rows as $row) {
            if (! $row instanceof FixedAssetImportRow || ! $row->isValid()) {
                $skipped++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $organization, &$imported): void {
                    FixedAsset::create([
                        'organization_id' => $organization->id,
                        'name' => $row->name,
                        'description' => $row->description,
                        'acquisition_date' => $row->acquisitionDate,
                        'acquisition_cost' => $row->acquisitionCost,
                        'depreciation_method' => $row->depreciationMethod,
                        'depreciation_rate' => $row->depreciationRate,
                        'useful_life_years' => $row->usefulLifeYears,
                        'residual_value' => $row->residualValue ?? '0',
                        'accumulated_depreciation' => $row->accumulatedDepreciation ?? '0',
                    ]);

                    $imported++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $rowNum = $row->sourceRow();
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                Log::warning('Migration import: fixed asset row failed', [
                    'row' => $rowNum,
                    'error' => $e->getMessage(),
                    'organization_id' => $organization->id,
                ]);
            }
        }

        if ($imported === 0 && $failed > 0) {
            return ImportResult::failure($this->dataType(), $errors, failed: $failed);
        }

        return ImportResult::success($this->dataType(), $imported, $skipped, warnings: $errors, failed: $failed);
    }
}
