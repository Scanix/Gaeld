<?php

namespace App\Domains\Organizations\DTOs;

use App\Domains\Organizations\Enums\BusinessType;
use App\Support\AddressData;
use App\Support\ValidatesFromArray;

/**
 * DTO for creating a new organization during setup or provisioning.
 */
readonly class CreateOrganizationData
{
    use ValidatesFromArray;

    public function __construct(
        public string $name,
        public ?string $legalName = null,
        public ?AddressData $addressData = null,
        public string $country = 'CH',
        public ?string $vatNumber = null,
        public string $currency = 'CHF',
        public string $fiscalYearStart = '01-01',
        public string $locale = 'en',
        public ?BusinessType $businessType = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

        return new self(
            name: $data['name'],
            legalName: $data['legal_name'] ?? null,
            addressData: AddressData::fromArray($data, includeCanton: true, defaultCountry: $data['country'] ?? 'CH'),
            country: $data['country'] ?? 'CH',
            vatNumber: $data['vat_number'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            fiscalYearStart: $data['fiscal_year_start'] ?? '01-01',
            locale: $data['locale'] ?? 'en',
            businessType: isset($data['business_type']) ? BusinessType::tryFrom($data['business_type']) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'legal_name' => $this->legalName ?? $this->name,
            'country' => $this->country,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'fiscal_year_start' => $this->fiscalYearStart,
            'locale' => $this->locale,
            'business_type' => $this->businessType?->value,
        ] + ($this->addressData?->toArray(includeCanton: true) ?? AddressData::empty(includeCanton: true)->toArray(includeCanton: true));
    }
}
