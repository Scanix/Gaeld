<?php

namespace App\Domains\Contacts\DTOs;

use App\Support\AddressData;

readonly class CreateSupplierData
{
    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $type = 'organization',
        public ?AddressData $addressData = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $vatNumber = null,
        public ?string $defaultExpenseCategory = null,
        public ?string $currency = null,
        public ?string $iban = null,
        public ?string $internalNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId: $data['organization_id'],
            name: $data['name'],
            type: $data['type'] ?? 'organization',
            addressData: AddressData::fromArray($data),
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            defaultExpenseCategory: $data['default_expense_category'] ?? null,
            currency: $data['currency'] ?? null,
            iban: $data['iban'] ?? null,
            internalNotes: $data['internal_notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone,
            'vat_number' => $this->vatNumber,
            'default_expense_category' => $this->defaultExpenseCategory,
            'currency' => $this->currency,
            'iban' => $this->iban,
            'internal_notes' => $this->internalNotes,
        ] + ($this->addressData?->toArray() ?? AddressData::empty()->toArray());
    }
}
