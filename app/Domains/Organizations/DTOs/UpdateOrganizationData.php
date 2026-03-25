<?php

namespace App\Domains\Organizations\DTOs;

use App\Support\AddressData;

readonly class UpdateOrganizationData
{
    public function __construct(
        public string $name,
        public ?string $legalName = null,
        public ?AddressData $addressData = null,
        public ?string $vatNumber = null,
        public string $currency = 'CHF',
        public string $locale = 'en',
        public int $defaultPaymentTermsDays = 30,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            legalName: $data['legal_name'] ?? null,
            addressData: AddressData::fromArray($data, includeCanton: true),
            vatNumber: $data['vat_number'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            locale: $data['locale'] ?? 'en',
            defaultPaymentTermsDays: (int) ($data['default_payment_terms_days'] ?? 30),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'default_payment_terms_days' => $this->defaultPaymentTermsDays,
        ] + ($this->addressData?->toArray(includeCanton: true) ?? AddressData::empty(includeCanton: true)->toArray(includeCanton: true));
    }
}