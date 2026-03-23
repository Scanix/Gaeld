<?php

namespace Tests\Unit\DTOs;

use App\Domains\Contacts\DTOs\CreateSupplierData;
use PHPUnit\Framework\TestCase;

class CreateSupplierDataTest extends TestCase
{
    public function test_from_array_with_required_fields(): void
    {
        $dto = CreateSupplierData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Supplier AG',
        ]);

        $this->assertSame('org-1', $dto->organizationId);
        $this->assertSame('Supplier AG', $dto->name);
        $this->assertNull($dto->iban);
        $this->assertNull($dto->defaultExpenseCategory);
    }

    public function test_from_array_includes_supplier_specific_fields(): void
    {
        $dto = CreateSupplierData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Supplier AG',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'default_expense_category' => 'office_supplies',
        ]);

        $this->assertSame('CH93 0076 2011 6238 5295 7', $dto->iban);
        $this->assertSame('office_supplies', $dto->defaultExpenseCategory);
    }

    public function test_to_array_includes_all_fields(): void
    {
        $dto = CreateSupplierData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Test',
        ]);

        $array = $dto->toArray();
        $this->assertArrayHasKey('iban', $array);
        $this->assertArrayHasKey('default_expense_category', $array);
        $this->assertArrayHasKey('organization_id', $array);
    }
}
