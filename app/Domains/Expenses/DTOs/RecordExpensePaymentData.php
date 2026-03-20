<?php

namespace App\Domains\Expenses\DTOs;

readonly class RecordExpensePaymentData
{
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
        return new self(
            amount: (string) $data['amount'],
            paymentDate: $data['payment_date'],
            reference: $data['reference'],
            description: $data['description'],
            expenseAccountCode: $data['expense_account_code'],
            bankAccountCode: $data['bank_account_code'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'payment_date' => $this->paymentDate,
            'reference' => $this->reference,
            'description' => $this->description,
            'expense_account_code' => $this->expenseAccountCode,
            'bank_account_code' => $this->bankAccountCode,
        ];
    }
}
