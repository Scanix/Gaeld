<?php

namespace App\Domains\Contacts\DTOs;

readonly class UpdateCustomerData
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public ?string $vatNumber = null,
        public ?string $currency = null,
        public ?string $paymentTerms = null,
        public ?string $internalNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            country: $data['country'] ?? null,
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
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'payment_terms' => $this->paymentTerms,
            'internal_notes' => $this->internalNotes,
        ], fn ($value) => $value !== null);
    }
}
