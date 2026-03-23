<?php

namespace App\Domains\Invoicing\DTOs;

readonly class InvoiceLineData
{
    public function __construct(
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public ?string $vatRateId = null,
        public ?int $sortOrder = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            quantity: $data['quantity'],
            unitPrice: $data['unit_price'],
            vatRateId: $data['vat_rate_id'] ?? null,
            sortOrder: $data['sort_order'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'vat_rate_id' => $this->vatRateId,
            'sort_order' => $this->sortOrder,
        ];
    }
}
