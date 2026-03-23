<?php

namespace App\Domains\Expenses\DTOs;

readonly class CreateExpenseData
{
    public function __construct(
        public string $organizationId,
        public string $category,
        public string $amount,
        public string $date,
        public ?string $description = null,
        public ?string $vatAmount = null,
        public ?string $vatRateId = null,
        public ?string $vendor = null,
        public ?string $receiptPath = null,
        public string $currency = 'CHF',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId: $data['organization_id'],
            category: $data['category'],
            amount: (string) $data['amount'],
            date: $data['date'],
            description: $data['description'] ?? null,
            vatAmount: isset($data['vat_amount']) ? (string) $data['vat_amount'] : null,
            vatRateId: $data['vat_rate_id'] ?? null,
            vendor: $data['vendor'] ?? null,
            receiptPath: $data['receipt_path'] ?? null,
            currency: $data['currency'] ?? 'CHF',
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'vat_rate_id' => $this->vatRateId,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'vat_amount' => $this->vatAmount,
            'date' => $this->date,
            'vendor' => $this->vendor,
            'receipt_path' => $this->receiptPath,
            'currency' => $this->currency,
        ];
    }
}
