<?php

namespace App\Domains\Assets\DTOs;

readonly class CreateFixedAssetData
{
    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $description,
        public string $purchaseDate,
        public string $purchaseAmount,
        public int $usefulLifeYears,
        public string $salvageValue,
        public string $depreciationMethod,
        public int $assetAccountId,
        public int $depreciationExpenseAccountId,
        public int $accumulatedDepreciationAccountId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId: $data['organization_id'],
            name: $data['name'],
            description: $data['description'] ?? null,
            purchaseDate: $data['purchase_date'],
            purchaseAmount: $data['purchase_amount'],
            usefulLifeYears: (int) $data['useful_life_years'],
            salvageValue: $data['salvage_value'] ?? '0.00',
            depreciationMethod: $data['depreciation_method'] ?? 'linear',
            assetAccountId: (int) $data['asset_account_id'],
            depreciationExpenseAccountId: (int) $data['depreciation_expense_account_id'],
            accumulatedDepreciationAccountId: (int) $data['accumulated_depreciation_account_id'],
        );
    }
}
