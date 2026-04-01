<?php

namespace App\Domains\Banking\DTOs;

use App\Support\MapsToSnakeCase;

/**
 * DTO for creating a new bank account linked to an organization.
 */
readonly class CreateBankAccountData
{
    use MapsToSnakeCase;

    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $iban = null,
        public ?string $bankName = null,
        public ?string $accountId = null,
        public string $currency = 'CHF',
        public string $balance = '0',
        public bool $isMixedUse = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            organizationId: $data['organization_id'],
            name: $data['name'],
            iban: $data['iban'] ?? null,
            bankName: $data['bank_name'] ?? null,
            accountId: $data['account_id'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            balance: isset($data['balance']) ? (string) $data['balance'] : '0',
            isMixedUse: $data['is_mixed_use'] ?? false,
        );
    }
}
