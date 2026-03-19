<?php

namespace Tests\Unit;

use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use PHPUnit\Framework\TestCase;

class InvoiceDtoTest extends TestCase
{
    public function test_create_invoice_data_from_array(): void
    {
        $dto = CreateInvoiceData::fromArray([
            'organization_id' => 'org-1',
            'customer_id' => 'customer-1',
            'number' => 'INV-001',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'notes' => 'Test note',
            'lines' => [
                ['description' => 'Service', 'quantity' => 5, 'unit_price' => 100, 'vat_rate_id' => 'vat-1'],
            ],
        ]);

        $this->assertEquals('org-1', $dto->organizationId);
        $this->assertEquals('customer-1', $dto->customerId);
        $this->assertEquals('INV-001', $dto->number);
        $this->assertEquals('CHF', $dto->currency);
        $this->assertCount(1, $dto->lines);
        $this->assertEquals('Service', $dto->lines[0]['description']);
    }

    public function test_create_invoice_data_defaults_currency_to_chf(): void
    {
        $dto = CreateInvoiceData::fromArray([
            'organization_id' => 'org-1',
            'customer_id' => 'customer-1',
            'number' => 'INV-002',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50],
            ],
        ]);

        $this->assertEquals('CHF', $dto->currency);
    }

    public function test_create_invoice_data_to_array(): void
    {
        $dto = CreateInvoiceData::fromArray([
            'organization_id' => 'org-1',
            'customer_id' => 'customer-1',
            'number' => 'INV-005',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'EUR',
            'notes' => 'Note',
            'payment_terms' => '30 days',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        $array = $dto->toArray();

        $this->assertEquals('org-1', $array['organization_id']);
        $this->assertEquals('customer-1', $array['customer_id']);
        $this->assertEquals('EUR', $array['currency']);
        $this->assertEquals('Note', $array['notes']);
    $this->assertCount(1, $array['lines']);
    $this->assertEquals('Service', $array['lines'][0]['description']);
    }

    public function test_record_payment_data_from_valid_request(): void
    {
        $dto = RecordPaymentData::fromArray([
            'amount' => '500.00',
            'payment_date' => '2026-04-01',
            'payment_method' => 'bank',
            'reference' => 'PAY-001',
        ]);

        $this->assertEquals('500.00', $dto->amount);
        $this->assertEquals('2026-04-01', $dto->paymentDate);
        $this->assertEquals('bank', $dto->paymentMethod);
        $this->assertEquals('PAY-001', $dto->reference);
    }

    public function test_record_payment_data_to_array(): void
    {
        $dto = RecordPaymentData::fromArray([
            'amount' => '500.00',
            'payment_date' => '2026-04-01',
            'payment_method' => 'cash',
            'reference' => 'PAY-002',
            'bank_account_code' => '1020',
        ]);

        $this->assertSame([
            'amount' => '500.00',
            'payment_date' => '2026-04-01',
            'payment_method' => 'cash',
            'reference' => 'PAY-002',
            'bank_account_code' => '1020',
        ], $dto->toArray());
    }

    public function test_update_invoice_data_from_valid_request(): void
    {
        $dto = UpdateInvoiceData::fromArray([
            'organization_id' => 'org-1',
            'customer_id' => 'customer-1',
            'number' => 'INV-UPDATED',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-03-01',
            'lines' => [
                ['description' => 'Updated service', 'quantity' => 2, 'unit_price' => 200],
            ],
        ]);

        $this->assertInstanceOf(UpdateInvoiceData::class, $dto);
        $this->assertEquals('INV-UPDATED', $dto->number);
        $this->assertCount(1, $dto->lines);
    }
}
