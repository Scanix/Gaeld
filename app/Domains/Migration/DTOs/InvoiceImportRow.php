<?php

namespace App\Domains\Migration\DTOs;

class InvoiceImportRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $number,
        public readonly string $date,
        public readonly ?string $dueDate,
        public readonly string $status,
        public readonly string $customerName,
        public readonly ?string $customerEmail = null,
        public readonly string $currency = 'CHF',
        public readonly ?string $description = null,
        /** @var array<array{description: string, quantity: float, unit_price: float, vat_rate: ?float}> */
        public readonly array $lines = [],
        public readonly ?string $totalAmount = null,
        public readonly ?string $paidAmount = null,
        public readonly ?string $paidDate = null,
        public readonly ?string $reference = null,
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'date' => $this->date,
            'due_date' => $this->dueDate,
            'status' => $this->status,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'currency' => $this->currency,
            'description' => $this->description,
            'lines' => $this->lines,
            'total_amount' => $this->totalAmount,
            'paid_amount' => $this->paidAmount,
            'paid_date' => $this->paidDate,
            'reference' => $this->reference,
        ];
    }
}
