<?php

namespace App\Domains\Contacts\DTOs;

use App\Support\MapsToSnakeCase;

/**
 * DTO for updating an existing contact person's details.
 */
readonly class UpdateContactPersonData
{
    use MapsToSnakeCase;
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $position = null,
        public bool $isPrimary = false,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            position: $data['position'] ?? null,
            isPrimary: $data['is_primary'] ?? false,
            notes: $data['notes'] ?? null,
        );
    }

}
