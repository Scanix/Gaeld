<?php

namespace App\Domains\Payroll\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for creating a new employee record.
 */
readonly class CreateEmployeeData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $organizationId,
        public string $firstName,
        public string $lastName,
        public string $entryDate,
        public string $grossSalary,
        public ?string $email = null,
        public ?string $ahvNumber = null,
        public ?string $exitDate = null,
        public bool $isActive = true,
        public bool $isSourceTaxSubject = false,
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
            ahvNumber: $data['ahv_number'] ?? null,
            exitDate: $data['exit_date'] ?? null,
            isActive: $data['is_active'] ?? true,
            isSourceTaxSubject: $data['is_source_tax_subject'] ?? false,
        );
    }
}
