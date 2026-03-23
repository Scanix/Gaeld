<?php

namespace App\Domains\Contacts\DTOs;

use App\Support\AddressData;

readonly class UpdateCustomerData
{
    public function __construct(
        public string $name,
        public ?AddressData $addressData = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $vatNumber = null,
        public ?string $currency = null,
        public ?string $paymentTerms = null,
        public ?string $internalNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            addressData: AddressData::fromArray($data),
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            currency: $data['currency'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            internalNotes: $data['internal_notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'payment_terms' => $this->paymentTerms,
            'internal_notes' => $this->internalNotes,
        ] + ($this->addressData?->toArray() ?? AddressData::empty()->toArray()), fn ($value) => $value !== null);
    }
}
