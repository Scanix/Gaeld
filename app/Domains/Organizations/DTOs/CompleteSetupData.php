<?php

namespace App\Domains\Organizations\DTOs;

use App\Domains\Users\DTOs\CreateUserData;

/**
 * Composite DTO wrapping user and organization data for the initial setup wizard.
 */
readonly class CompleteSetupData
{
    public function __construct(
        public CreateUserData $user,
        public CreateOrganizationData $organization,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user: CreateUserData::fromArray([
                'name' => $data['user_name'],
                'email' => $data['user_email'],
                'password' => $data['user_password'],
                'locale' => $data['locale'],
                'email_verified_at' => now(),
            ]),
            organization: CreateOrganizationData::fromArray([
                'name' => $data['org_name'],
                'legal_name' => $data['org_legal_name'] ?? $data['org_name'],
                'address' => $data['org_address'] ?? null,
                'city' => $data['org_city'] ?? null,
                'postal_code' => $data['org_postal_code'] ?? null,
                'canton' => $data['org_canton'] ?? null,
                'currency' => $data['currency'],
                'locale' => $data['locale'],
            ]),
        );
    }
}
