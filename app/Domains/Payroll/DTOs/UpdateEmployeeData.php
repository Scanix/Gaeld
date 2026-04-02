<?php

namespace App\Domains\Payroll\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for updating an existing employee record.
 */
readonly class UpdateEmployeeData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
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

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['first_name', 'last_name', 'entry_date', 'gross_salary']);

        return new self(
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
