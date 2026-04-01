<?php

namespace App\Domains\Migration\DTOs;

class FixedAssetImportRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $name,
        public readonly string $acquisitionDate,
        public readonly string $acquisitionCost,
        public readonly string $depreciationMethod,
        public readonly ?float $depreciationRate = null,
        public readonly ?int $usefulLifeYears = null,
        public readonly ?string $residualValue = null,
        public readonly ?string $accumulatedDepreciation = null,
        public readonly ?string $accountCode = null,
        public readonly ?string $depreciationAccountCode = null,
        public readonly ?string $description = null,
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'acquisition_date' => $this->acquisitionDate,
            'acquisition_cost' => $this->acquisitionCost,
            'depreciation_method' => $this->depreciationMethod,
            'depreciation_rate' => $this->depreciationRate,
            'useful_life_years' => $this->usefulLifeYears,
            'residual_value' => $this->residualValue,
            'accumulated_depreciation' => $this->accumulatedDepreciation,
            'account_code' => $this->accountCode,
            'depreciation_account_code' => $this->depreciationAccountCode,
            'description' => $this->description,
        ];
    }
}
