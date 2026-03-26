<?php

namespace App\Domains\Contacts\DTOs;

readonly class CreateContactPersonData
{
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

    public function toArray(): array
    {
        return [
            'contactable_type' => $this->contactableType,
            'contactable_id' => $this->contactableId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'is_primary' => $this->isPrimary,
            'notes' => $this->notes,
        ];
    }
}
