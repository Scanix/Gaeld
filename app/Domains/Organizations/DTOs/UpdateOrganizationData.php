<?php

namespace App\Domains\Organizations\DTOs;

readonly class UpdateOrganizationData
{
    public function __construct(
        public string $name,
        public ?string $legalName = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $canton = null,
        public ?string $vatNumber = null,
        public string $currency = 'CHF',
        public string $locale = 'en',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            legalName: $data['legal_name'] ?? null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            canton: $data['canton'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            locale: $data['locale'] ?? 'en',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'canton' => $this->canton,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'locale' => $this->locale,
        ];
    }
}