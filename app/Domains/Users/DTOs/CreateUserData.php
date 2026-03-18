<?php

namespace App\Domains\Users\DTOs;

readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $locale = 'en',
        public mixed $emailVerifiedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            locale: $data['locale'] ?? 'en',
            emailVerifiedAt: $data['email_verified_at'] ?? null,
        );
    }
}