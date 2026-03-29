<?php

namespace App\Domains\Contacts\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for adding a contact person to a customer or supplier.
 */
readonly class CreateContactPersonData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $contactableType,
        public string $contactableId,
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
        self::assertRequired($data, ['contactable_type', 'contactable_id', 'first_name', 'last_name']);

        return new self(
            contactableType: $data['contactable_type'],
            contactableId: $data['contactable_id'],
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
