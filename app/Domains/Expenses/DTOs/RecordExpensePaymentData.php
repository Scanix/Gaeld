<?php

namespace App\Domains\Expenses\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for recording a payment against an expense.
 */
readonly class RecordExpensePaymentData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $amount,
        public string $paymentDate,
        public string $reference,
        public string $description,
        public string $expenseAccountCode,
        public ?string $bankAccountCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['amount', 'payment_date', 'reference', 'description', 'expense_account_code']);

        return new self(
            amount: (string) $data['amount'],
            paymentDate: $data['payment_date'],
            reference: $data['reference'],
            description: $data['description'],
            expenseAccountCode: $data['expense_account_code'],
            bankAccountCode: $data['bank_account_code'] ?? null,
        );
    }

    public static function forReconciliation(
        string $amount,
        string $paymentDate,
        string $reference,
        string $transactionDescription,
        string $expenseDescription,
        string $expenseAccountCode,
        string $bankAccountCode,
    ): self {
        return new self(
            amount: $amount,
            paymentDate: $paymentDate,
            reference: $reference,
            description: "Reconciliation: {$transactionDescription} ↔ Expense {$expenseDescription}",
            expenseAccountCode: $expenseAccountCode,
            bankAccountCode: $bankAccountCode,
        );
    }
}
