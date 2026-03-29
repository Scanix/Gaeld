<?php

namespace App\Domains\Organizations\DTOs;

use App\Support\AddressData;
use App\Support\ValidatesFromArray;

/**
 * DTO carrying the validated fields needed to update an existing organization.
 *
 * Required fields: name.
 * All other fields are optional and fall back to sensible defaults so that
 * callers only need to supply the values they actually want to change.
 */
readonly class UpdateOrganizationData
{
    use ValidatesFromArray;

    /**
     * @param  string  $name  Display name of the organization (required).
     * @param  string|null  $legalName  Official registered legal name, if different from display name.
     * @param  AddressData|null  $addressData  Structured address (street, city, postal code, country, canton).
     * @param  string|null  $vatNumber  VAT / tax identification number.
     * @param  string  $currency  ISO 4217 currency code used as the organization default (e.g. 'CHF').
     * @param  string  $locale  BCP 47 locale tag for UI language and number formatting (e.g. 'en').
     * @param  int  $defaultPaymentTermsDays  Number of days after invoice date before payment is due.
     */
    public function __construct(
        public string $name,
        public ?string $legalName = null,
        public ?AddressData $addressData = null,
        public ?string $vatNumber = null,
        public string $currency = 'CHF',
        public string $locale = 'en',
        public int $defaultPaymentTermsDays = 30,
    ) {}

    /**
     * Construct an instance from a raw associative array (e.g. a validated request payload).
     *
     * The key `name` is mandatory; all other keys are optional and fall back to
     * the constructor defaults when absent.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException when the `name` key is missing.
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name']);

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

    /**
     * Serialize the DTO to a flat associative array suitable for Eloquent mass-assignment.
     *
     * Address fields are merged at the top level and null values are stripped to
     * avoid NOT NULL constraint violations caused by ConvertEmptyStringsToNull
     * turning empty strings into null for fields such as `country`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Filter null address fields to avoid NOT NULL constraint violations
        // (e.g. ConvertEmptyStringsToNull turns "" into null for country).
        $address = $this->addressData?->toArray(includeCanton: true) ?? [];
        $address = array_filter($address, fn ($value) => $value !== null);

        return [
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'vat_number' => $this->vatNumber,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'default_payment_terms_days' => $this->defaultPaymentTermsDays,
        ] + $address;
    }
}
