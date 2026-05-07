<?php

namespace Tests\Unit;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\SwissQrInvoiceService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwissQrInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private SwissQrInvoiceService $service;

    private Organization $org;

    private Contact $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SwissQrInvoiceService;

        $this->org = Organization::create([
            'name' => 'Test GmbH',
            'legal_name' => 'Test GmbH',
            'address' => 'Bahnhofstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        BankAccount::create([
            'organization_id' => $this->org->id,
            'name' => 'QR-Bill account',
            'currency' => 'CHF',
            'qr_iban' => 'CH4431999123000889012',
            'is_default_for_invoicing' => true,
            'is_active' => true,
        ]);

        $this->client = Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Client AG',
            'address' => 'Lagerstrasse 5',
            'postal_code' => '8004',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);
    }

    public function test_generates_qr_reference(): void
    {
        $ref = $this->service->generateQrReference('00000', '2026001');

        $this->assertNotEmpty($ref);
        $this->assertEquals(27, strlen($ref));
        $this->assertMatchesRegularExpression('/^\d{27}$/', $ref);
    }

    public function test_generates_deterministic_qr_reference(): void
    {
        $ref1 = $this->service->generateQrReference('00000', '2026001');
        $ref2 = $this->service->generateQrReference('00000', '2026001');

        $this->assertEquals($ref1, $ref2);
    }

    public function test_builds_qr_bill(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->client->id,
            'number' => 'INV-2026-001',
            'status' => 'sent',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'subtotal' => '1000.00',
            'vat_amount' => '81.00',
            'total' => '1081.00',
            'qr_reference' => $this->service->generateQrReference('00000', '2026001'),
            'qr_type' => 'QRR',
            'qr_iban' => 'CH4431999123000889012',
        ]);

        $qrBill = $this->service->buildQrBill($invoice, $this->org);

        $this->assertNotNull($qrBill);

        $violations = $qrBill->getViolations();
        $this->assertCount(0, $violations, 'QR Bill should have no violations: '.implode(', ', array_map(fn ($v) => $v->getMessage(), iterator_to_array($violations))));
    }

    public function test_validate_returns_no_errors_for_valid_invoice(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->client->id,
            'number' => 'INV-2026-002',
            'status' => 'sent',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'subtotal' => '500.00',
            'vat_amount' => '0.00',
            'total' => '500.00',
            'qr_reference' => $this->service->generateQrReference('00000', '2026002'),
            'qr_type' => 'QRR',
            'qr_iban' => 'CH4431999123000889012',
        ]);

        $errors = $this->service->validate($invoice, $this->org);

        $this->assertEmpty($errors, 'Expected no validation errors but got: '.implode(', ', $errors));
    }

    public function test_supports_non_reference_type(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->client->id,
            'number' => 'INV-2026-003',
            'status' => 'sent',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'subtotal' => '200.00',
            'vat_amount' => '0.00',
            'total' => '200.00',
            'qr_type' => 'NON',
            'qr_iban' => 'CH9300762011623852957',
        ]);

        $qrBill = $this->service->buildQrBill($invoice, $this->org);
        $violations = $qrBill->getViolations();

        $this->assertCount(0, $violations, 'NON ref violations: '.implode(', ', array_map(fn ($v) => $v->getMessage(), iterator_to_array($violations))));
    }

    public function test_supports_eur_currency(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->client->id,
            'number' => 'INV-2026-004',
            'status' => 'sent',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'EUR',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'qr_type' => 'NON',
            'qr_iban' => 'CH9300762011623852957',
        ]);

        $qrBill = $this->service->buildQrBill($invoice, $this->org);
        $violations = $qrBill->getViolations();

        $this->assertCount(0, $violations, 'EUR violations: '.implode(', ', array_map(fn ($v) => $v->getMessage(), iterator_to_array($violations))));
    }

    public function test_auto_generates_qr_reference_for_qr_iban(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->client->id,
            'number' => 'INV-2026-005',
            'status' => 'sent',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'subtotal' => '750.00',
            'vat_amount' => '0.00',
            'total' => '750.00',
            'qr_iban' => 'CH4431999123000889012',
        ]);

        $this->assertNull($invoice->qr_reference);

        $qrBill = $this->service->buildQrBill($invoice, $this->org);
        $violations = $qrBill->getViolations();

        $invoice->refresh();
        $this->assertNotNull($invoice->qr_reference);
        $this->assertEquals('QRR', $invoice->qr_type);
        $this->assertEquals(27, strlen($invoice->qr_reference));
        $this->assertCount(0, $violations, 'Auto-generated QR ref violations: '.implode(', ', array_map(fn ($v) => $v->getMessage(), iterator_to_array($violations))));
    }
}
