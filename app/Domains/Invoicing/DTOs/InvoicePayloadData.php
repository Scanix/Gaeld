<?php

namespace App\Domains\Invoicing\DTOs;

abstract readonly class InvoicePayloadData
{
    /**
     * @param array<int, InvoiceLineData> $lines
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

    public static function fromArray(array $data): static
    {
        return new static(
            organizationId: $data['organization_id'],
            customerId: $data['customer_id'],
            number: $data['number'],
            issueDate: $data['issue_date'],
            dueDate: $data['due_date'],
            currency: $data['currency'] ?? 'CHF',
            notes: $data['notes'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            lines: array_map(fn (array $line) => InvoiceLineData::fromArray($line), $data['lines']),
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
            'lines' => array_map(fn (InvoiceLineData $line) => $line->toArray(), $this->lines),
        ];
    }
}