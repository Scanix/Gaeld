<?php

namespace App\Domains\Expenses\DTOs;

use App\Support\OmitsNullValues;
use App\Support\ValidatesFromArray;

/**
 * DTO for updating an existing expense.
 */
readonly class UpdateExpenseData
{
    use OmitsNullValues;
    use ValidatesFromArray;

    public function __construct(
        public string $category,
        public string $amount,
        public string $date,
        public ?string $description = null,
        public ?string $vatAmount = null,
        public ?string $vatRateId = null,
        public ?string $vendor = null,
        public ?string $receiptPath = null,
        public ?string $currency = null,
        public ?string $type = null,
        public ?string $expenseAccountCode = null,
        public ?string $bankAccountCode = null,
        public ?string $supplierId = null,
        public ?string $paymentMethod = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['category', 'amount', 'date']);

        return new self(
            category: $data['category'],
            amount: (string) $data['amount'],
            date: $data['date'],
            description: $data['description'] ?? null,
            vatAmount: isset($data['vat_amount']) ? (string) $data['vat_amount'] : null,
            vatRateId: $data['vat_rate_id'] ?? null,
            vendor: $data['vendor'] ?? null,
            receiptPath: $data['receipt_path'] ?? null,
            currency: $data['currency'] ?? null,
            type: $data['type'] ?? null,
            expenseAccountCode: $data['expense_account_code'] ?? null,
            bankAccountCode: $data['bank_account_code'] ?? null,
            supplierId: $data['supplier_id'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
        );
    }
}
