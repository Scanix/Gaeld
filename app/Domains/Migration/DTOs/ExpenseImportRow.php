<?php

namespace App\Domains\Migration\DTOs;

class ExpenseImportRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $date,
        public readonly string $amount,
        public readonly string $currency = 'CHF',
        public readonly ?string $description = null,
        public readonly ?string $category = null,
        public readonly ?string $supplierName = null,
        public readonly ?string $supplierEmail = null,
        public readonly ?string $accountCode = null,
        public readonly ?float $vatRate = null,
        public readonly ?string $reference = null,
        public readonly bool $isPaid = true,
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'category' => $this->category,
            'supplier_name' => $this->supplierName,
            'supplier_email' => $this->supplierEmail,
            'account_code' => $this->accountCode,
            'vat_rate' => $this->vatRate,
            'reference' => $this->reference,
            'is_paid' => $this->isPaid,
        ];
    }
}
