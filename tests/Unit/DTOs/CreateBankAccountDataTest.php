<?php

namespace Tests\Unit\DTOs;

use App\Domains\Banking\DTOs\CreateBankAccountData;
use PHPUnit\Framework\TestCase;

class CreateBankAccountDataTest extends TestCase
{
    public function test_from_array_with_required_fields(): void
    {
        $dto = CreateBankAccountData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Main Account',
        ]);

        $this->assertSame('org-1', $dto->organizationId);
        $this->assertSame('Main Account', $dto->name);
        $this->assertSame('CHF', $dto->currency);
        $this->assertSame('0', $dto->balance);
    }

    public function test_from_array_with_all_fields(): void
    {
        $dto = CreateBankAccountData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Business Account',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'bank_name' => 'UBS',
            'account_id' => 'acc-1',
            'currency' => 'EUR',
            'balance' => 1500.00,
        ]);

        $this->assertSame('CH93 0076 2011 6238 5295 7', $dto->iban);
        $this->assertSame('UBS', $dto->bankName);
        $this->assertSame('EUR', $dto->currency);
        $this->assertSame('1500', $dto->balance);
    }

    public function test_to_array_includes_correct_keys(): void
    {
        $dto = CreateBankAccountData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Test',
        ]);

        $array = $dto->toArray();
        $this->assertArrayHasKey('organization_id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('iban', $array);
        $this->assertArrayHasKey('bank_name', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('balance', $array);
    }
}
