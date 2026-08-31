<?php

namespace App\Domains\Invoicing\Enums;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Validation\ValidationException;

enum InvoiceTaxTreatment: string
{
    case Standard = 'standard';
    case ReverseCharge = 'reverse_charge';

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return [
            ['value' => self::Standard->value, 'label' => __('app.invoice_tax_treatment_standard')],
            ['value' => self::ReverseCharge->value, 'label' => __('app.invoice_tax_treatment_reverse_charge')],
        ];
    }

    public function appliesSwissVat(): bool
    {
        return $this === self::Standard;
    }

    public function validateCustomer(?Contact $customer): void
    {
        if ($this !== self::ReverseCharge) {
            return;
        }

        if ($customer === null || ! self::isEuCountry($customer->country)) {
            throw ValidationException::withMessages([
                'tax_treatment' => [__('app.invoice_reverse_charge_eu_customer_required')],
            ]);
        }

        if (! self::hasValidEuVatNumber($customer->country, $customer->vat_number)) {
            throw ValidationException::withMessages([
                'tax_treatment' => [__('app.invoice_reverse_charge_vat_number_required')],
            ]);
        }
    }

    public static function isEuCountry(?string $country): bool
    {
        return in_array(strtoupper((string) $country), [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
            'FR', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
            'PT', 'RO', 'SE', 'SI', 'SK',
        ], true);
    }

    public static function hasValidEuVatNumber(?string $country, ?string $vatNumber): bool
    {
        if (! self::isEuCountry($country) || blank($vatNumber)) {
            return false;
        }

        $normalizedVatNumber = strtoupper((string) preg_replace('/[\s.-]+/', '', $vatNumber));
        $countryCode = strtoupper((string) $country);
        $acceptedCountryCodes = $countryCode === 'GR' ? ['GR', 'EL'] : [$countryCode];

        return preg_match('/^([A-Z]{2})([A-Z0-9]{2,14})$/', $normalizedVatNumber, $matches) === 1
            && in_array($matches[1], $acceptedCountryCodes, true);
    }
}
