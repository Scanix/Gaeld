<?php

namespace App\Domains\Banking\DTOs;

readonly class CreateBankAccountData
{
    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $iban = null,
        public ?string $bankName = null,
        public ?string $accountId = null,
        public string $currency = 'CHF',
        public string $balance = '0',
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
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'account_id' => $this->accountId,
            'name' => $this->name,
            'iban' => $this->iban,
            'bank_name' => $this->bankName,
            'currency' => $this->currency,
            'balance' => $this->balance,
        ];
    }
}
