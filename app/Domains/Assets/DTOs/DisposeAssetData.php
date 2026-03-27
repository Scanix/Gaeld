<?php

namespace App\Domains\Assets\DTOs;

readonly class DisposeAssetData
{
    public function __construct(
        public string $disposalAmount,
        public string $disposalDate,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            disposalAmount: $data['disposal_amount'],
            disposalDate: $data['disposal_date'],
        );
    }
}
