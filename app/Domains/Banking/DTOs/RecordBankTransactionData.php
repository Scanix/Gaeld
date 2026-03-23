<?php

namespace App\Domains\Banking\DTOs;

use App\Domains\Banking\Enums\BankTransactionType;

readonly class RecordBankTransactionData
{
    public function __construct(
        public string $date,
        public string $amount,
        public BankTransactionType $type,
        public ?string $description = null,
        public ?string $reference = null,
        public string $contraAccountCode = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            amount: (string) $data['amount'],
            type: $data['type'] instanceof BankTransactionType ? $data['type'] : BankTransactionType::from($data['type']),
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            contraAccountCode: $data['contra_account_code'],
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'amount' => $this->amount,
            'type' => $this->type->value,
            'description' => $this->description,
            'reference' => $this->reference,
            'contra_account_code' => $this->contraAccountCode,
        ];
    }
}
