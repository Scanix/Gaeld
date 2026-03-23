<?php

namespace App\Domains\Invoicing\DTOs;

use App\Domains\Invoicing\Enums\PaymentMethod;

readonly class RecordPaymentData
{
    public function __construct(
        public string $amount,
        public string $paymentDate,
        public PaymentMethod $paymentMethod,
        public ?string $reference,
        public ?string $bankAccountCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (string) $data['amount'],
            paymentDate: $data['payment_date'],
            paymentMethod: PaymentMethod::from($data['payment_method']),
            reference: $data['reference'] ?? null,
            bankAccountCode: $data['bank_account_code'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'payment_date' => $this->paymentDate,
            'payment_method' => $this->paymentMethod->value,
            'reference' => $this->reference,
            'bank_account_code' => $this->bankAccountCode,
        ];
    }
}
