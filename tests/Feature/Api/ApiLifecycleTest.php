<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Enums\TokenType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * API lifecycle and workflow endpoint tests.
 *
 * Covers: Contact CRUD, Invoice lifecycle (finalize/cancel/record-payment/send/reminder/credit-note),
 * and Expense workflow (approve/post-to-ledger).
 */
class ApiLifecycleTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);

        $this->setUpOrganization();

        // Organization fields required for Swiss QR bill PDF generation
        $this->org->update([
            'address' => 'Bahnhofstrasse 1',
            'postal_code' => '8001',
            'city' => 'Zürich',
            'iban' => 'CH9300762011623852957',
            'qr_iban' => 'CH4431999123000889012',
        ]);

        // Required accounting accounts for invoice lifecycle operations
        foreach ([
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value],
            ['code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value],
            ['code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value],
            ['code' => '3900', 'name' => 'Rounding', 'type' => AccountType::Revenue->value],
        ] as $account) {
            Account::create(array_merge($account, ['organization_id' => $this->org->id]));
        }

        VatRate::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        Mail::fake();

        $sanctumToken = $this->user->createToken('lifecycle-test', ['*']);
        $sanctumToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Personal,
        ]);
        $this->token = $sanctumToken->plainTextToken;
    }

    // ──────────────────────────────────────────────────────────────
    //  Supplier CRUD
    // ──────────────────────────────────────────────────────────────

    public function test_list_suppliers(): void
    {
        Contact::factory()->for($this->org, 'organization')->create(['name' => 'Digitec AG']);

        $this->withToken($this->token)
            ->getJson('/api/v1/suppliers')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'country', 'currency', 'created_at'],
                ],
            ]);
    }

    public function test_show_supplier(): void
    {
        $supplier = Contact::factory()->for($this->org, 'organization')->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/suppliers/{$supplier->getRouteKey()}")
            ->assertOk()
            ->assertJsonPath('data.id', $supplier->uuid);
    }

    public function test_create_supplier(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/suppliers', [
                'name' => 'New Supplier GmbH',
                'email' => 'billing@newsupplier.ch',
                'country' => 'CH',
                'currency' => 'CHF',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'New Supplier GmbH');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'New Supplier GmbH',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_update_supplier(): void
    {
        $supplier = Contact::factory()->for($this->org, 'organization')->create(['name' => 'Old Name']);

        $this->withToken($this->token)
            ->putJson("/api/v1/suppliers/{$supplier->getRouteKey()}", [
                'name' => 'Updated Name',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_delete_supplier(): void
    {
        $supplier = Contact::factory()->for($this->org, 'organization')->create();

        $this->withToken($this->token)
            ->deleteJson("/api/v1/suppliers/{$supplier->getRouteKey()}")
            ->assertStatus(204);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_from_other_org_not_accessible(): void
    {
        $otherSupplier = Contact::factory()->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/suppliers/{$otherSupplier->getRouteKey()}")
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    //  Invoice Lifecycle
    // ──────────────────────────────────────────────────────────────

    public function test_finalize_invoice(): void
    {
        $invoice = $this->makeDraftInvoice();

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Sent->value);
    }

    public function test_cancel_invoice(): void
    {
        $invoice = $this->makeSentInvoice();

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Cancelled->value);
    }

    public function test_record_payment(): void
    {
        $invoice = $this->makeSentInvoice();

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/record-payment", [
                'amount' => '100.00',
                'payment_date' => '2026-04-13',
                'payment_method' => 'bank',
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'amount_paid']]);
    }

    public function test_send_invoice(): void
    {
        $invoice = $this->makeSentInvoice();

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/send")
            ->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::Sent->value);
    }

    public function test_reminder_invoice(): void
    {
        $invoice = $this->makeOverdueInvoice();

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/reminder")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'status']]);
    }

    public function test_credit_note_from_invoice(): void
    {
        $invoice = $this->makeSentInvoice();

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/credit-note")
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'credit_note');
    }

    public function test_finalize_invalid_state_returns_422(): void
    {
        $invoice = $this->makeSentInvoice(); // already sent, cannot finalize again

        $this->withToken($this->token)
            ->postJson("/api/v1/invoices/{$invoice->getRouteKey()}/finalize")
            ->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────
    //  Expense Workflow
    // ──────────────────────────────────────────────────────────────

    public function test_approve_expense(): void
    {
        $expense = Expense::factory()->for($this->org, 'organization')->create([
            'status' => ExpenseStatus::Pending->value,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/expenses/{$expense->getRouteKey()}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', ExpenseStatus::Approved->value);
    }

    public function test_approve_already_approved_expense_returns_422(): void
    {
        $expense = Expense::factory()->for($this->org, 'organization')->create([
            'status' => ExpenseStatus::Approved->value,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/expenses/{$expense->getRouteKey()}/approve")
            ->assertStatus(422);
    }

    public function test_post_to_ledger_requires_approved_expense(): void
    {
        $expense = Expense::factory()->for($this->org, 'organization')->create([
            'status' => ExpenseStatus::Pending->value,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/expenses/{$expense->getRouteKey()}/post-to-ledger", [
                'expense_account_code' => '6000',
            ])
            ->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function makeCustomer(): Contact
    {
        return Contact::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'country' => 'CH',
            'currency' => 'CHF',
            'email' => 'customer@example.ch',
            'address' => 'Bundesplatz 3',
            'postal_code' => '3003',
            'city' => 'Bern',
        ]);
    }

    private function makeDraftInvoice(): Invoice
    {
        $customer = $this->makeCustomer();

        $invoice = Invoice::factory()
            ->for($this->org, 'organization')
            ->for($customer)
            ->create([
                'status' => InvoiceStatus::Draft,
                'subtotal' => '100.00',
                'vat_amount' => '0.00',
                'total' => '100.00',
            ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'amount' => 100.00,
            'sort_order' => 1,
        ]);

        return $invoice;
    }

    private function makeSentInvoice(): Invoice
    {
        $customer = $this->makeCustomer();

        $invoice = Invoice::factory()
            ->for($this->org, 'organization')
            ->for($customer)
            ->sent()
            ->create();

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'amount' => 100.00,
            'sort_order' => 1,
        ]);

        return $invoice;
    }

    private function makeOverdueInvoice(): Invoice
    {
        $customer = $this->makeCustomer();

        $invoice = Invoice::factory()
            ->for($this->org, 'organization')
            ->for($customer)
            ->create([
                'status' => InvoiceStatus::Sent,
                'due_date' => now()->subDays(10)->toDateString(),
            ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'amount' => 100.00,
            'sort_order' => 1,
        ]);

        return $invoice;
    }
}
