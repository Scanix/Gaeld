<?php

namespace App\Domains\Assets\DTOs;

/**
 * DTO carrying disposal amount and date when retiring a fixed asset.
 */
readonly class DisposeAssetData
{
    public function __construct(
        public string $disposalAmount,
        public string $disposalDate,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            disposalAmount: $data['disposal_amount'],
            disposalDate: $data['disposal_date'],
        );
    }
}
