<?php

namespace App\Domains\Invoicing\DTOs;

readonly class CreateInvoiceData
{
    /**
     * @param array<int, array{description: string, quantity: string, unit_price: string, vat_rate_id: ?string, sort_order?: int}> $lines
     */
    public function __construct(
        public string $organizationId,
        public string $customerId,
        public string $number,
        public string $issueDate,
        public string $dueDate,
        public string $currency,
        public ?string $notes,
        public ?string $paymentTerms,
        public array $lines,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId: $data['organization_id'],
            customerId: $data['customer_id'],
            number: $data['number'],
            issueDate: $data['issue_date'],
            dueDate: $data['due_date'],
            currency: $data['currency'] ?? 'CHF',
            notes: $data['notes'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            lines: $data['lines'],
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'customer_id' => $this->customerId,
            'number' => $this->number,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'payment_terms' => $this->paymentTerms,
        ];
    }
}
