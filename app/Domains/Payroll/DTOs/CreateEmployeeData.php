<?php

namespace App\Domains\Payroll\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for creating a new employee record.
 */
readonly class CreateEmployeeData
{
    use MapsToSnakeCase {
        toArray as private toSnakeCaseArray;
    }
    use ValidatesFromArray;

    public function __construct(
        public string $organizationId,
        public string $firstName,
        public string $lastName,
        public string $entryDate,
        public string $grossSalary,
        public ?string $email = null,
        public ?string $iban = null,
        public ?string $ahvNumber = null,
        public ?string $exitDate = null,
        public bool $isActive = true,
        public bool $isSourceTaxSubject = false,
        public bool $hasThirteenthSalary = false,
        public ?string $sourceTaxCanton = null,
        public ?string $sourceTaxTariff = null,
        public ?string $sourceTaxMunicipalityCode = null,
        private bool $sourceTaxFieldsProvided = false,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['organization_id', 'first_name', 'last_name', 'entry_date', 'gross_salary']);

        return new self(
            organizationId: $data['organization_id'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            entryDate: $data['entry_date'],
            grossSalary: (string) $data['gross_salary'],
            email: $data['email'] ?? null,
            iban: $data['iban'] ?? null,
            ahvNumber: $data['ahv_number'] ?? null,
            exitDate: $data['exit_date'] ?? null,
            isActive: $data['is_active'] ?? true,
            isSourceTaxSubject: $data['is_source_tax_subject'] ?? false,
            hasThirteenthSalary: $data['has_thirteenth_salary'] ?? false,
            sourceTaxCanton: isset($data['source_tax_canton']) ? strtoupper((string) $data['source_tax_canton']) : null,
            sourceTaxTariff: isset($data['source_tax_tariff']) ? strtoupper((string) $data['source_tax_tariff']) : null,
            sourceTaxMunicipalityCode: $data['source_tax_municipality_code'] ?? null,
            sourceTaxFieldsProvided: array_key_exists('source_tax_canton', $data)
                || array_key_exists('source_tax_tariff', $data)
                || array_key_exists('source_tax_municipality_code', $data),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = $this->toSnakeCaseArray();
        unset($data['source_tax_fields_provided']);

        if (! $this->sourceTaxFieldsProvided) {
            unset($data['source_tax_canton'], $data['source_tax_tariff'], $data['source_tax_municipality_code']);
        }

        return $data;
    }
}
