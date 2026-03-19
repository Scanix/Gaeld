<?php

namespace Tests\Unit\DTOs;

use App\Domains\Contacts\DTOs\CreateCustomerData;
use PHPUnit\Framework\TestCase;

class CreateCustomerDataTest extends TestCase
{
    public function test_from_array_with_required_fields(): void
    {
        $dto = CreateCustomerData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Test Customer',
        ]);

        $this->assertSame('org-1', $dto->organizationId);
        $this->assertSame('Test Customer', $dto->name);
        $this->assertNull($dto->email);
        $this->assertNull($dto->vatNumber);
    }

    public function test_from_array_with_all_fields(): void
    {
        $dto = CreateCustomerData::fromArray([
            'organization_id' => 'org-1',
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '+41 79 123 45 67',
            'address' => 'Bahnhofstrasse 1',
            'city' => 'Zürich',
            'postal_code' => '8001',
            'country' => 'CH',
            'vat_number' => 'CHE-123.456.789',
            'currency' => 'CHF',
            'payment_terms' => '30 days',
            'internal_notes' => 'VIP',
        ]);

        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('Zürich', $dto->city);
        $this->assertSame('CHE-123.456.789', $dto->vatNumber);
    }

    public function test_to_array_roundtrips(): void
    {
        $input = [
            'organization_id' => 'org-1',
            'name' => 'Test',
            'email' => null,
            'phone' => null,
            'address' => null,
            'city' => null,
            'postal_code' => null,
            'country' => null,
            'vat_number' => null,
            'currency' => null,
            'payment_terms' => null,
            'internal_notes' => null,
        ];

        $dto = CreateCustomerData::fromArray($input);
        $this->assertSame($input, $dto->toArray());
    }
}
