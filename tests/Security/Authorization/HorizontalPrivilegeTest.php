<?php

namespace Tests\Security\Authorization;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Tests\Security\SecurityTestCase;

/**
 * IDOR (Insecure Direct Object Reference) tests.
 *
 * Every test creates a resource belonging to Org B and attempts to access
 * it as Org A's user. The response must be 403 or 404 — never 200.
 *
 * 403 = explicit Policy denial.
 * 404 = resource filtered out by the BelongsToOrganization global scope.
 * Both are acceptable security controls.
 */
class HorizontalPrivilegeTest extends SecurityTestCase
{
    private Customer $customerB;

    private Invoice $invoiceB;

    private Expense $expenseB;

    private BankAccount $bankAccountB;

    private Account $accountB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Org B resources bypassing the global scope so they land in orgB
        $this->customerB = Customer::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Org B Customer',
        ]);

        $arAccountB = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'code' => '1100',
            'name' => 'Org B AR',
            'type' => AccountType::Asset->value,
        ]);

        $this->invoiceB = Invoice::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'customer_id' => $this->customerB->id,
            'number' => 'INV-B-001',
            'status' => InvoiceStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'CHF',
            'subtotal' => '1000.00',
            'tax_amount' => '81.00',
            'total' => '1081.00',
        ]);

        $bankAccountBankAccount = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'code' => '1020',
            'name' => 'Org B Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->bankAccountB = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'account_id' => $bankAccountBankAccount->id,
            'name' => 'Org B Bank Account',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);

        $expenseAccountB = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'code' => '6000',
            'name' => 'Org B Expense',
            'type' => AccountType::Expense->value,
        ]);

        $this->expenseB = Expense::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'category' => 'office',
            'description' => 'Org B Expense',
            'amount' => '500.00',
            'vat_amount' => '0.00',
            'currency' => 'CHF',
            'date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->accountB = $arAccountB;
    }

    // ──────────────────────────────────────────────────────────────
    //  Customers (CRUD)
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_view_other_org_customer(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->get("/customers/{$this->customerB->uuid}")
        );
    }

    public function test_cannot_update_other_org_customer(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->put("/customers/{$this->customerB->uuid}", ['name' => 'Hacked'])
        );
    }

    public function test_cannot_delete_other_org_customer(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->delete("/customers/{$this->customerB->uuid}")
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Invoices (CRUD + actions)
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_view_other_org_invoice(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->get("/invoices/{$this->invoiceB->id}")
        );
    }

    public function test_cannot_update_other_org_invoice(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->put("/invoices/{$this->invoiceB->id}", ['number' => 'HACKED'])
        );
    }

    public function test_cannot_delete_other_org_invoice(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->delete("/invoices/{$this->invoiceB->id}")
        );
    }

    public function test_cannot_finalize_other_org_invoice(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->post("/invoices/{$this->invoiceB->id}/finalize")
        );
    }

    public function test_cannot_download_other_org_invoice_pdf(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->get("/invoices/{$this->invoiceB->id}/qr-pdf")
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Expenses
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_view_other_org_expense(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->get("/expenses/{$this->expenseB->id}")
        );
    }

    public function test_cannot_update_other_org_expense(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->put("/expenses/{$this->expenseB->id}", ['description' => 'Hacked'])
        );
    }

    public function test_cannot_delete_other_org_expense(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->delete("/expenses/{$this->expenseB->id}")
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Banking
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_view_other_org_bank_account(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->get("/banking/{$this->bankAccountB->uuid}")
        );
    }

    public function test_cannot_import_camt_to_other_org_bank_account(): void
    {
        $file = UploadedFile::fake()->create('evil.xml', 1, 'application/xml');

        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->post("/reconciliation/{$this->bankAccountB->uuid}/import", [
                    'camt_file' => $file,
                ])
        );
    }

    public function test_cannot_view_other_org_reconciliation(): void
    {
        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->get("/reconciliation/{$this->bankAccountB->uuid}")
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  REST API — cross-org with a token scoped to Org A
    // ──────────────────────────────────────────────────────────────

    public function test_api_cannot_fetch_other_org_invoice(): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $this->withToken($token)
            ->getJson("/api/v1/invoices/{$this->invoiceB->id}")
            ->assertStatus(404); // Filtered by global scope; orgB resource is invisible
    }

    public function test_api_cannot_fetch_other_org_customer(): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $this->withToken($token)
            ->getJson("/api/v1/customers/{$this->customerB->uuid}")
            ->assertStatus(404);
    }

    public function test_api_cannot_fetch_other_org_expense(): void
    {
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $this->withToken($token)
            ->getJson("/api/v1/expenses/{$this->expenseB->id}")
            ->assertStatus(404);
    }

    public function test_api_list_does_not_leak_other_org_resources(): void
    {
        // Org A token should only list Org A's customers — Org B's must not appear
        $token = $this->createApiToken($this->ownerA, $this->orgA);

        $response = $this->withToken($token)->getJson('/api/v1/customers');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains(
            (string) $this->customerB->id,
            $ids,
            'Org B customer must not appear in Org A API response'
        );
    }
}
