<?php

namespace App\Domains\Invoicing\DTOs;

use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Support\ValidatesFromArray;
use Illuminate\Support\Arr;

/**
 * Abstract base DTO shared by CreateInvoiceData and UpdateInvoiceData.
 *
 * Contains the common invoice header fields and an array of line items.
 */
abstract readonly class InvoicePayloadData
{
    use ValidatesFromArray;

    public const COMMERCIAL_REFERENCE_FIELDS = ['customer_reference', 'purchase_order_reference', 'delivery_reference'];

    /** @var array<string, string|null> */
    public array $commercialReferences;

    /**
     * @param  array<int, InvoiceLineData>  $lines
     * @param  array<string, string|null>  $commercialReferences
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
        public InvoiceTaxTreatment $taxTreatment = InvoiceTaxTreatment::Standard,
        array $commercialReferences = [],
        public ?string $externalSource = null,
        public ?string $externalId = null,
    ) {
        $this->commercialReferences = Arr::only($commercialReferences, self::COMMERCIAL_REFERENCE_FIELDS);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): static
    {
        static::assertRequired($data, ['organization_id', 'number', 'issue_date', 'lines']);

        return new static(
            organizationId: $data['organization_id'],
            commercialReferences: Arr::only($data, self::COMMERCIAL_REFERENCE_FIELDS),
            customerId: $data['customer_id'] ?? null,
            number: $data['number'],
            issueDate: $data['issue_date'],
            dueDate: $data['due_date'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            notes: $data['notes'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            lines: array_map(fn (array $line) => InvoiceLineData::fromArray($line), $data['lines']),
            externalSource: $data['external_source'] ?? null,
            externalId: $data['external_id'] ?? null,
            taxTreatment: InvoiceTaxTreatment::tryFrom($data['tax_treatment'] ?? InvoiceTaxTreatment::Standard->value)
                ?? InvoiceTaxTreatment::Standard,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->commercialReferences,
            ...($this->externalSource !== null ? ['external_source' => $this->externalSource, 'external_id' => $this->externalId] : []),
            'organization_id' => $this->organizationId,
            'customer_id' => $this->customerId,
            'number' => $this->number,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'payment_terms' => $this->paymentTerms,
            'lines' => array_map(fn (InvoiceLineData $line) => $line->toArray(), $this->lines),
            'tax_treatment' => $this->taxTreatment->value,
        ];
    }
}
