<?php

namespace App\Domains\Invoicing\DTOs;

use App\Support\ValidatesFromArray;

/**
 * Abstract base DTO shared by CreateInvoiceData and UpdateInvoiceData.
 *
 * Contains the common invoice header fields and an array of line items.
 */
abstract readonly class InvoicePayloadData
{
    use ValidatesFromArray;

    /**
     * @param  array<int, InvoiceLineData>  $lines
     */
    public function __construct(
        public string $organizationId,
        public ?string $customerId,
        public string $number,
        public string $issueDate,
        public ?string $dueDate,
        public string $currency,
        public ?string $notes,
        public ?string $paymentTerms,
        public array $lines,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): static
    {
        static::assertRequired($data, ['organization_id', 'number', 'issue_date', 'lines']);

        return new static(
            organizationId: $data['organization_id'],
            customerId: $data['customer_id'] ?? null,
            number: $data['number'],
            issueDate: $data['issue_date'],
            dueDate: $data['due_date'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            notes: $data['notes'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            lines: array_map(fn (array $line) => InvoiceLineData::fromArray($line), $data['lines']),
        );
    }

    /** @return array<string, mixed> */
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
