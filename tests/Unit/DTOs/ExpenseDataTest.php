<?php

namespace Tests\Unit\DTOs;

use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use PHPUnit\Framework\TestCase;

class ExpenseDataTest extends TestCase
{
    public function test_create_from_array_casts_amount_to_string(): void
    {
        $dto = CreateExpenseData::fromArray([
            'organization_id' => 'org-1',
            'category' => 'office',
            'amount' => 99.95,
            'date' => '2025-01-15',
        ]);

        $this->assertSame('99.95', $dto->amount);
        $this->assertSame('CHF', $dto->currency);
    }

    public function test_create_from_array_handles_vat(): void
    {
        $dto = CreateExpenseData::fromArray([
            'organization_id' => 'org-1',
            'category' => 'office',
            'amount' => 107.70,
            'date' => '2025-01-15',
            'vat_amount' => 7.70,
            'vat_rate_id' => 'vat-1',
        ]);

        $this->assertSame('7.7', $dto->vatAmount);
        $this->assertSame('vat-1', $dto->vatRateId);
    }

    public function test_create_to_array_roundtrips(): void
    {
        $dto = CreateExpenseData::fromArray([
            'organization_id' => 'org-1',
            'category' => 'travel',
            'amount' => '250.00',
            'date' => '2025-03-01',
            'vendor' => 'SBB',
            'currency' => 'EUR',
        ]);

        $array = $dto->toArray();
        $this->assertSame('org-1', $array['organization_id']);
        $this->assertSame('travel', $array['category']);
        $this->assertSame('250.00', $array['amount']);
        $this->assertSame('EUR', $array['currency']);
        $this->assertSame('SBB', $array['vendor']);
    }

    public function test_update_from_array(): void
    {
        $dto = UpdateExpenseData::fromArray([
            'category' => 'meals',
            'amount' => 45.50,
            'date' => '2025-02-20',
            'description' => 'Team lunch',
        ]);

        $this->assertSame('meals', $dto->category);
        $this->assertSame('45.5', $dto->amount);
        $this->assertSame('Team lunch', $dto->description);
    }

    public function test_update_to_array_does_not_include_organization_id(): void
    {
        $dto = UpdateExpenseData::fromArray([
            'category' => 'office',
            'amount' => '10.00',
            'date' => '2025-01-01',
        ]);

        $array = $dto->toArray();
        $this->assertArrayNotHasKey('organization_id', $array);
        $this->assertArrayHasKey('category', $array);
    }
}
