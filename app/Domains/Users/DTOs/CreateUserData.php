<?php

namespace App\Domains\Users\DTOs;

use DateTimeInterface;

/**
 * DTO for user account creation (registration or admin-provisioned).
 */
readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $locale = 'en',
        public DateTimeInterface|string|null $emailVerifiedAt = null,
        public DateTimeInterface|string|null $acceptedPrivacyAt = null,
        public DateTimeInterface|string|null $acceptedTermsAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            locale: $data['locale'] ?? 'en',
            emailVerifiedAt: $data['email_verified_at'] ?? null,
            acceptedPrivacyAt: $data['accepted_privacy_at'] ?? null,
            acceptedTermsAt: $data['accepted_terms_at'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'locale' => $this->locale,
            'email_verified_at' => $this->emailVerifiedAt,
        ];
    }
}
