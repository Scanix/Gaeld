<?php

namespace Tests\Unit\DTOs;

use App\Domains\Accounting\DTOs\CreateAccountData;
use App\Domains\Accounting\Enums\AccountType;
use PHPUnit\Framework\TestCase;

class CreateAccountDataTest extends TestCase
{
    public function test_create_account_data_to_array(): void
    {
        $data = new CreateAccountData(
            organizationId: 'org-1',
            code: '1000',
            name: 'Cash',
            type: AccountType::Asset,
        );

        $this->assertSame([
            'organization_id' => 'org-1',
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
            'parent_id' => null,
            'description' => null,
            'is_active' => true,
        ], $data->toArray());
    }

    public function test_create_account_data_from_array(): void
    {
        $data = CreateAccountData::fromArray([
            'organization_id' => 'org-2',
            'code' => '2000',
            'name' => 'Liabilities',
            'type' => 'liability',
            'parent_id' => 'parent-1',
            'description' => 'General liabilities',
            'is_active' => false,
        ]);

        $this->assertSame('org-2', $data->organizationId);
        $this->assertSame('2000', $data->code);
        $this->assertSame('Liabilities', $data->name);
        $this->assertSame(AccountType::Liability, $data->type);
        $this->assertSame('parent-1', $data->parentId);
        $this->assertSame('General liabilities', $data->description);
        $this->assertFalse($data->isActive);
    }

    public function test_create_account_data_defaults(): void
    {
        $data = new CreateAccountData(
            organizationId: 'org-1',
            code: '1000',
            name: 'Cash',
            type: AccountType::Asset,
        );

        $this->assertNull($data->parentId);
        $this->assertNull($data->description);
        $this->assertTrue($data->isActive);
    }
}
