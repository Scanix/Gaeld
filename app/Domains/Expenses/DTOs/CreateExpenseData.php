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
        public ?int $userId = null,
        public ?string $description = null,
        public ?string $vatAmount = null,
        public ?string $vatRateId = null,
        public ?string $vendor = null,
        public ?string $supplierId = null,
        public ?string $receiptPath = null,
        public string $currency = 'CHF',
        public string $type = 'invoice',
        public ?string $expenseAccountCode = null,
        public ?string $bankAccountCode = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['organization_id', 'category', 'amount', 'date']);

        return new self(
            organizationId: $data['organization_id'],
            category: $data['category'],
            amount: (string) $data['amount'],
            date: $data['date'],
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            description: $data['description'] ?? null,
            vatAmount: isset($data['vat_amount']) ? (string) $data['vat_amount'] : null,
            vatRateId: $data['vat_rate_id'] ?? null,
            vendor: $data['vendor'] ?? null,
            supplierId: $data['supplier_id'] ?? null,
            receiptPath: $data['receipt_path'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            type: $data['type'] ?? 'invoice',
            expenseAccountCode: $data['expense_account_code'] ?? null,
            bankAccountCode: $data['bank_account_code'] ?? null,
        );
    }
}
