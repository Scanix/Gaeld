<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Enums\TokenType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Contract tests for all public API endpoints not covered by ApiTest.
 *
 * Covers: invoices, expenses, accounts (read-only), bank-accounts (read-only).
 * Auth types: Sanctum bearer token. Cookie auth is tested implicitly
 * through web feature tests in the Invoicing/Expenses domains.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);

        $this->setUpOrganization();

        $sanctumToken = $this->user->createToken('contract-test', ['*']);
        $sanctumToken->accessToken->update([
            'organization_id' => $this->org->id,
            'type' => TokenType::Personal,
        ]);
        $this->token = $sanctumToken->plainTextToken;
    }

    // ──────────────────────────────────────────────────────────────
    //  Invoices
    // ──────────────────────────────────────────────────────────────

    public function test_list_invoices(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Invoice Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        Invoice::factory()->for($this->org, 'organization')->for($customer)->create();

        $this->withToken($this->token)
            ->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'number', 'status', 'type', 'total', 'currency', 'created_at'],
                ],
            ]);
    }

    public function test_create_invoice(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Billed Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->uuid,
                'issue_date' => '2026-04-07',
                'due_date' => '2026-05-07',
                'currency' => 'CHF',
                'lines' => [
                    [
                        'description' => 'Contract test line',
                        'quantity' => 1,
                        'unit_price' => '100.00',
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'number', 'status', 'total', 'lines'],
            ]);
    }

    public function test_show_invoice(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Show Invoice Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $invoice = Invoice::factory()->for($this->org, 'organization')->for($customer)->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    }

    public function test_update_invoice(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Update Invoice Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $invoice = Invoice::factory()->for($this->org, 'organization')->for($customer)->create([
            'notes' => 'old notes',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'customer_id' => $customer->uuid,
                'number' => $invoice->number,
                'issue_date' => $invoice->issue_date->toDateString(),
                'notes' => 'updated notes',
                'lines' => [
                    [
                        'description' => 'Updated line',
                        'quantity' => 1,
                        'unit_price' => '100.00',
                    ],
                ],
            ])
            ->assertOk();
    }

    public function test_update_invoice_with_partial_payload(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Partial Update Invoice Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $createResponse = $this->withToken($this->token)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->uuid,
                'issue_date' => '2026-04-07',
                'due_date' => '2026-05-07',
                'currency' => 'CHF',
                'lines' => [
                    [
                        'description' => 'Initial line',
                        'quantity' => 1,
                        'unit_price' => '100.00',
                    ],
                ],
            ])
            ->assertStatus(201);

        $invoiceId = (string) $createResponse->json('data.id');

        $this->withToken($this->token)
            ->putJson("/api/v1/invoices/{$invoiceId}", [
                'notes' => 'updated from partial payload',
            ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'updated from partial payload')
            ->assertJsonPath('data.lines.0.description', 'Initial line');
    }

    public function test_delete_invoice(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->org->id,
            'name' => 'Delete Invoice Customer',
            'country' => 'CH',
            'currency' => 'CHF',
        ]);

        $invoice = Invoice::factory()->for($this->org, 'organization')->for($customer)->create();

        $this->withToken($this->token)
            ->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(204);
    }

    public function test_invoice_from_other_org_not_accessible(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'currency' => 'CHF',
        ]);

        $customer = new Customer;
        $customer->organization_id = $otherOrg->id;
        $customer->name = 'Other Org Customer';
        $customer->country = 'CH';
        $customer->currency = 'CHF';
        $customer->saveQuietly();

        $invoice = Invoice::factory()->for($otherOrg, 'organization')->for($customer)->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    //  Expenses
    // ──────────────────────────────────────────────────────────────

    public function test_list_expenses(): void
    {
        Expense::factory()->for($this->org, 'organization')->create();

        $this->withToken($this->token)
            ->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'category', 'amount', 'date', 'status', 'currency'],
                ],
            ]);
    }

    public function test_create_expense(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/expenses', [
                'category' => 'office_supplies',
                'description' => 'Printer paper',
                'amount' => '89.50',
                'date' => '2026-04-05',
                'currency' => 'CHF',
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'category', 'amount', 'status'],
            ]);
    }

    public function test_show_expense(): void
    {
        $expense = Expense::factory()->for($this->org, 'organization')->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id);
    }

    public function test_update_expense(): void
    {
        $expense = Expense::factory()->for($this->org, 'organization')->create();

        $this->withToken($this->token)
            ->putJson("/api/v1/expenses/{$expense->id}", [
                'category' => $expense->category,
                'amount' => $expense->amount,
                'date' => $expense->date->toDateString(),
                'description' => 'Updated description',
            ])
            ->assertOk();
    }

    public function test_delete_expense(): void
    {
        $expense = Expense::factory()->for($this->org, 'organization')->create();

        $this->withToken($this->token)
            ->deleteJson("/api/v1/expenses/{$expense->id}")
            ->assertStatus(204);
    }

    // ──────────────────────────────────────────────────────────────
    //  Accounts (read-only)
    // ──────────────────────────────────────────────────────────────

    public function test_list_accounts(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/accounts')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'type', 'is_active'],
                ],
            ]);
    }

    public function test_show_account(): void
    {
        $account = Account::query()
            ->where('organization_id', $this->org->id)
            ->first();

        if (! $account) {
            $this->markTestSkipped('No accounts seeded for this org.');
        }

        $this->withToken($this->token)
            ->getJson("/api/v1/accounts/{$account->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $account->uuid);
    }

    public function test_accounts_are_read_only(): void
    {
        // POST should not exist (405 or 404)
        $this->withToken($this->token)
            ->postJson('/api/v1/accounts', ['name' => 'Hack'])
            ->assertStatus(405);
    }

    // ──────────────────────────────────────────────────────────────
    //  Bank Accounts (read-only)
    // ──────────────────────────────────────────────────────────────

    public function test_list_bank_accounts(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/bank-accounts')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_show_bank_account(): void
    {
        $bankAccount = BankAccount::query()
            ->where('organization_id', $this->org->id)
            ->first();

        if (! $bankAccount) {
            $this->markTestSkipped('No bank accounts seeded for this org.');
        }

        $this->withToken($this->token)
            ->getJson("/api/v1/bank-accounts/{$bankAccount->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $bankAccount->uuid);
    }

    public function test_bank_accounts_are_read_only(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/bank-accounts', ['name' => 'Hack'])
            ->assertStatus(405);
    }

    // ──────────────────────────────────────────────────────────────
    //  Validation & Error Contracts
    // ──────────────────────────────────────────────────────────────

    public function test_invoice_validation_returns_422(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/invoices', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_expense_validation_returns_422(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/expenses', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_nonexistent_resource_returns_404(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/invoices/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    //  Pagination Contract
    // ──────────────────────────────────────────────────────────────

    public function test_invoice_list_is_paginated(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/invoices?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_customer_list_is_paginated(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/customers?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
