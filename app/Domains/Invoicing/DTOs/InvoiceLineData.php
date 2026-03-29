<?php

namespace App\Domains\Invoicing\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for a single line item on an invoice.
 */
readonly class InvoiceLineData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public ?string $vatRateId = null,
        public ?int $sortOrder = null,
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['description', 'quantity', 'unit_price']);

        return new self(
            description: $data['description'],
            quantity: $data['quantity'],
            unitPrice: $data['unit_price'],
            vatRateId: $data['vat_rate_id'] ?? null,
            sortOrder: $data['sort_order'] ?? null,
        );
    }

}
