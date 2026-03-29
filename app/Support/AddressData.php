<?php

namespace App\Support;

/**
 * Reusable value object for a physical address (street, city, postal code, country, canton).
 *
 * Shared by organization, customer, and supplier DTOs.
 */
readonly class AddressData
{
    public function __construct(
        public ?string $address = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public ?string $canton = null,
    ) {}

    public static function fromArray(array $data, bool $includeCanton = false, ?string $defaultCountry = null): ?self
    {
        $address = $data['address'] ?? null;
        $city = $data['city'] ?? null;
        $postalCode = $data['postal_code'] ?? null;
        $country = $data['country'] ?? $defaultCountry;
        $canton = $includeCanton ? ($data['canton'] ?? null) : null;

        if ($address === null && $city === null && $postalCode === null && $country === null && $canton === null) {
            return null;
        }

        return new self(
            address: $address,
            city: $city,
            postalCode: $postalCode,
            country: $country,
            canton: $canton,
        );
    }

    public static function empty(bool $includeCanton = false): self
    {
        return new self(canton: $includeCanton ? null : null);
    }

    public function toArray(bool $includeCanton = false): array
    {
        $data = [
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
        ];

        if ($includeCanton) {
            $data['canton'] = $this->canton;
        }

        return $data;
    }
}
