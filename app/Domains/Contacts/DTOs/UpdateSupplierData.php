<?php

namespace App\Domains\Contacts\DTOs;

use App\Support\AddressData;

/**
 * DTO for updating an existing supplier record.
 */
readonly class UpdateSupplierData
{
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?AddressData $addressData = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $vatNumber = null,
        public ?string $defaultExpenseCategory = null,
        public ?string $currency = null,
        public ?string $iban = null,
        public ?string $internalNotes = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'] ?? null,
            addressData: AddressData::fromArray($data),
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            defaultExpenseCategory: $data['default_expense_category'] ?? null,
            currency: $data['currency'] ?? null,
            iban: $data['iban'] ?? null,
            internalNotes: $data['internal_notes'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone,
            'vat_number' => $this->vatNumber,
            'default_expense_category' => $this->defaultExpenseCategory,
            'currency' => $this->currency,
            'iban' => $this->iban,
            'internal_notes' => $this->internalNotes,
            'notes' => $this->notes ? ['default' => $this->notes] : null,
        ] + ($this->addressData?->toArray() ?? AddressData::empty()->toArray()), fn ($value) => $value !== null);
    }
}
