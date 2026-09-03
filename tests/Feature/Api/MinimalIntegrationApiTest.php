<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Enums\TokenType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Enums\InvoiceType;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use App\Domains\Invoicing\Services\SwissQrInvoiceService;
use App\Domains\Organizations\Enums\Permission;
use Tests\Security\SecurityTestCase;

class MinimalIntegrationApiTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_it_creates_a_bank_account_from_an_active_account_code(): void
    {
        $account = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'PostFinance',
            'iban' => 'CH9300762011623852957',
            'qr_iban' => 'CH4431999123000889012',
            'currency' => 'CHF',
            'account_code' => '1020',
        ];
        $response = $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'bank-account-001')
            ->postJson('/api/v1/bank-accounts', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'PostFinance')
            ->assertJsonPath('data.account_id', $account->uuid)
            ->assertJsonPath('data.account_code', '1020')
            ->assertJsonPath('data.qr_iban', 'CH4431999123000889012');

        $this->assertDatabaseHas('bank_accounts', [
            'organization_id' => $this->orgA->id,
            'name' => 'PostFinance',
            'account_id' => $account->id,
        ]);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'bank-account-001')
            ->postJson('/api/v1/bank-accounts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $response->json('data.id'));

        $this->assertDatabaseCount('bank_accounts', 1);
    }

    public function test_it_rejects_a_gl_account_from_another_organization(): void
    {
        Account::create([
            'organization_id' => $this->orgB->id,
            'code' => '1020',
            'name' => 'Other bank',
            'type' => AccountType::Asset->value,
            'is_active' => true,
        ]);

        $this->withToken($this->tokenA)
            ->postJson('/api/v1/bank-accounts', [
                'name' => 'Foreign account',
                'account_code' => '1020',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_it_requires_a_linked_active_gl_account(): void
    {
        $this->withToken($this->tokenA)
            ->postJson('/api/v1/bank-accounts', ['name' => 'Unlinked account'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['account_code']]);

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_bank_account_creation_requires_the_banking_create_ability(): void
    {
        $result = $this->ownerA->createToken('read-only-token', [Permission::BankingView->value]);
        $result->accessToken->update([
            'organization_id' => $this->orgA->id,
            'type' => TokenType::Personal,
        ]);

        $this->withToken($result->plainTextToken)
            ->postJson('/api/v1/bank-accounts', ['name' => 'Unauthorized account'])
            ->assertForbidden();

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_it_hides_an_invoice_pdf_from_another_organization(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Private Client',
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->orgB->id,
            'customer_id' => $customer->id,
            'type' => InvoiceType::Invoice,
            'number' => 'INV-PRIVATE-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'subtotal' => '150.00',
            'vat_amount' => '0.00',
            'total' => '150.00',
        ]);

        $this->withToken($this->tokenA)
            ->getJson('/api/v1/invoices/'.$invoice->id.'/pdf')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_it_downloads_a_qr_invoice_pdf_with_api_authentication(): void
    {
        $this->orgA->update([
            'legal_name' => 'Security Org A',
            'address' => 'Bahnhofstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'country' => 'CH',
            'locale' => 'en',
        ]);
        BankAccount::create([
            'organization_id' => $this->orgA->id,
            'name' => 'QR account',
            'currency' => 'CHF',
            'qr_iban' => 'CH4431999123000889012',
            'is_default_for_invoicing' => true,
            'is_active' => true,
        ]);
        $customer = Contact::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Client AG',
            'address' => 'Lagerstrasse 5',
            'postal_code' => '8004',
            'city' => 'Zürich',
            'country' => 'CH',
        ]);
        $qrService = app(SwissQrInvoiceService::class);
        $invoice = Invoice::create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $customer->id,
            'type' => InvoiceType::Invoice,
            'number' => 'INV-API-PDF-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'subtotal' => '150.00',
            'vat_amount' => '0.00',
            'total' => '150.00',
            'qr_reference' => $qrService->generateQrReference('00000', '1'),
            'qr_type' => 'QRR',
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Membership services',
            'quantity' => '1.00',
            'unit_price' => '150.00',
            'amount' => '150.00',
            'vat_amount' => '0.00',
            'sort_order' => 0,
        ]);

        $response = $this->withToken($this->tokenA)
            ->get('/api/v1/invoices/'.$invoice->id.'/pdf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-inv-api-pdf-001.pdf"');
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF-', $content);
    }
}
