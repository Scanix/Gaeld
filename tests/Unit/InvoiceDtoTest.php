<?php

namespace Tests\Unit;

use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\DTOs\UpdateInvoiceData;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceDtoTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Customer $client;
    private VatRate $vatRate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'currency' => 'CHF',
        ]);

        $this->client = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Client',
        ]);

        $this->vatRate = VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);
    }

    public function test_create_invoice_data_from_valid_request(): void
    {
        $request = Request::create('/', 'POST', [
            'customer_id' => $this->client->id,
            'number' => 'INV-001',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'notes' => 'Test note',
            'lines' => [
                ['description' => 'Service', 'quantity' => 5, 'unit_price' => 100, 'vat_rate_id' => $this->vatRate->id],
            ],
        ]);

        $dto = CreateInvoiceData::fromRequest($request);

        $this->assertEquals($this->client->id, $dto->customerId);
        $this->assertEquals('INV-001', $dto->number);
        $this->assertEquals('CHF', $dto->currency);
        $this->assertCount(1, $dto->lines);
        $this->assertEquals('Service', $dto->lines[0]['description']);
    }

    public function test_create_invoice_data_defaults_currency_to_chf(): void
    {
        $request = Request::create('/', 'POST', [
            'customer_id' => $this->client->id,
            'number' => 'INV-002',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50],
            ],
        ]);

        $dto = CreateInvoiceData::fromRequest($request);

        $this->assertEquals('CHF', $dto->currency);
    }

    public function test_create_invoice_data_rejects_missing_lines(): void
    {
        $request = Request::create('/', 'POST', [
            'customer_id' => $this->client->id,
            'number' => 'INV-003',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
        ]);

        $this->expectException(ValidationException::class);
        CreateInvoiceData::fromRequest($request);
    }

    public function test_create_invoice_data_rejects_invalid_due_date(): void
    {
        $request = Request::create('/', 'POST', [
            'customer_id' => $this->client->id,
            'number' => 'INV-004',
            'issue_date' => '2026-03-15',
            'due_date' => '2026-01-01',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 50],
            ],
        ]);

        $this->expectException(ValidationException::class);
        CreateInvoiceData::fromRequest($request);
    }

    public function test_create_invoice_data_to_array(): void
    {
        $request = Request::create('/', 'POST', [
            'customer_id' => $this->client->id,
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

        $dto = CreateInvoiceData::fromRequest($request);
        $array = $dto->toArray();

        $this->assertEquals($this->client->id, $array['customer_id']);
        $this->assertEquals('EUR', $array['currency']);
        $this->assertEquals('Note', $array['notes']);
        $this->assertArrayNotHasKey('lines', $array);
    }

    public function test_record_payment_data_from_valid_request(): void
    {
        $request = Request::create('/', 'POST', [
            'amount' => '500.00',
            'payment_date' => '2026-04-01',
            'payment_method' => 'bank',
            'reference' => 'PAY-001',
        ]);

        $dto = RecordPaymentData::fromRequest($request);

        $this->assertEquals('500.00', $dto->amount);
        $this->assertEquals('2026-04-01', $dto->paymentDate);
        $this->assertEquals('bank', $dto->paymentMethod);
        $this->assertEquals('PAY-001', $dto->reference);
    }

    public function test_record_payment_data_rejects_invalid_method(): void
    {
        $request = Request::create('/', 'POST', [
            'amount' => '500.00',
            'payment_date' => '2026-04-01',
            'payment_method' => 'bitcoin',
        ]);

        $this->expectException(ValidationException::class);
        RecordPaymentData::fromRequest($request);
    }

    public function test_update_invoice_data_from_valid_request(): void
    {
        $request = Request::create('/', 'PUT', [
            'customer_id' => $this->client->id,
            'number' => 'INV-UPDATED',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-03-01',
            'lines' => [
                ['description' => 'Updated service', 'quantity' => 2, 'unit_price' => 200],
            ],
        ]);

        $dto = UpdateInvoiceData::fromRequest($request);

        $this->assertEquals('INV-UPDATED', $dto->number);
        $this->assertCount(1, $dto->lines);
    }
}
