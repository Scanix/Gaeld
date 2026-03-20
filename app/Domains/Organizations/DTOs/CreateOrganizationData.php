<?php

namespace App\Domains\Organizations\DTOs;

readonly class CreateOrganizationData
{
    public function __construct(
        public string $name,
        public ?string $legalName = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $canton = null,
        public string $country = 'CH',
        public ?string $vatNumber = null,
        public string $currency = 'CHF',
        public string $fiscalYearStart = '01-01',
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
            country: $data['country'] ?? 'CH',
            vatNumber: $data['vat_number'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            fiscalYearStart: $data['fiscal_year_start'] ?? '01-01',
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
            'country' => $this->country,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'fiscal_year_start' => $this->fiscalYearStart,
            'locale' => $this->locale,
        ];
    }
}