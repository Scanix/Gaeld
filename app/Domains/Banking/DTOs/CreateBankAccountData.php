<?php

namespace App\Domains\Banking\DTOs;

use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for creating a new bank account linked to an organization.
 */
readonly class CreateBankAccountData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $iban = null,
        public ?string $bankName = null,
        public ?string $bic = null,
        public ?string $accountId = null,
        public string $currency = 'CHF',
        public string $balance = '0',
        public bool $isMixedUse = false,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['organization_id', 'name']);

        return new self(
            organizationId: $data['organization_id'],
            name: $data['name'],
            iban: $data['iban'] ?? null,
            bankName: $data['bank_name'] ?? null,
            bic: $data['bic'] ?? null,
            accountId: $data['account_id'] ?? null,
            currency: $data['currency'] ?? 'CHF',
            balance: isset($data['balance']) ? (string) $data['balance'] : '0',
            isMixedUse: $data['is_mixed_use'] ?? false,
        );
    }
}
