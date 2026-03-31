<?php

namespace App\Domains\Expenses\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for recording a new business expense.
 */
readonly class CreateExpenseData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $organizationId,
        public string $category,
        public string $amount,
        public string $date,
        public ?string $description = null,
        public ?string $vatAmount = null,
        public ?string $vatRateId = null,
        public ?string $vendor = null,
        public ?string $supplierId = null,
        public ?string $receiptPath = null,
        public string $currency = 'CHF',
        public string $type = 'invoice',
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['organization_id', 'category', 'amount', 'date']);

        return new self(
            organizationId: $data['organization_id'],
            category: $data['category'],
            amount: (string) $data['amount'],
            date: $data['date'],
            description: $data['description'] ?? null,
            vatAmount: isset($data['vat_amount']) ? (string) $data['vat_amount'] : null,
            vatRateId: $data['vat_rate_id'] ?? null,
            vendor: $data['vendor'] ?? null,
            supplierId: $data['supplier_id'] ?? null,
            receiptPath: $data['receipt_path'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            type: $data['type'] ?? 'invoice',
        );
    }
}
