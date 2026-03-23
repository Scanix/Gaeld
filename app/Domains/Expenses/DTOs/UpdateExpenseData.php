<?php

namespace App\Domains\Expenses\DTOs;

readonly class UpdateExpenseData
{
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
    ) {}

    public static function fromArray(array $data): self
    {
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
        );
    }

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'vat_amount' => $this->vatAmount,
            'vat_rate_id' => $this->vatRateId,
            'date' => $this->date,
            'vendor' => $this->vendor,
            'receipt_path' => $this->receiptPath,
            'currency' => $this->currency,
        ];
    }
}
