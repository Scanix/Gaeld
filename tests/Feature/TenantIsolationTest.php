<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['name' => 'User A']);
        $this->userB = User::factory()->create(['name' => 'User B']);

        $this->orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $this->orgB = Organization::create(['name' => 'Org B', 'currency' => 'CHF']);

        $this->orgA->users()->attach($this->userA->id, ['role' => 'owner']);
        $this->orgB->users()->attach($this->userB->id, ['role' => 'owner']);
    }

    private function setCurrentOrg(Organization $org): void
    {
        app()->instance('current_organization', $org);
    }

    // ──────────────────────────────────────────────────────────────
    //  Bank Account Isolation
    // ──────────────────────────────────────────────────────────────

    public function test_bank_accounts_scoped_to_organization(): void
    {
        BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Org A Bank',
            'currency' => 'CHF',
        ]);

        BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Org B Bank',
            'currency' => 'CHF',
        ]);

        // As Org A
        $this->setCurrentOrg($this->orgA);
        $this->assertCount(1, BankAccount::all());
        $this->assertEquals('Org A Bank', BankAccount::first()->name);

        // As Org B
        $this->setCurrentOrg($this->orgB);
        $this->assertCount(1, BankAccount::all());
        $this->assertEquals('Org B Bank', BankAccount::first()->name);
    }

    // ──────────────────────────────────────────────────────────────
    //  Bank Import Isolation
    // ──────────────────────────────────────────────────────────────

    public function test_bank_imports_scoped_to_organization(): void
    {
        $bankAccountA = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Org A Bank',
            'currency' => 'CHF',
        ]);

        $bankAccountB = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Org B Bank',
            'currency' => 'CHF',
        ]);

        BankImport::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'bank_account_id' => $bankAccountA->id,
            'filename' => 'orgA.xml',
            'format' => 'camt053',
        ]);

        BankImport::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'bank_account_id' => $bankAccountB->id,
            'filename' => 'orgB.xml',
            'format' => 'camt053',
        ]);

        $this->setCurrentOrg($this->orgA);
        $imports = BankImport::all();
        $this->assertCount(1, $imports);
        $this->assertEquals('orgA.xml', $imports->first()->filename);

        $this->setCurrentOrg($this->orgB);
        $imports = BankImport::all();
        $this->assertCount(1, $imports);
        $this->assertEquals('orgB.xml', $imports->first()->filename);
    }

    // ──────────────────────────────────────────────────────────────
    //  Cross-tenant Access Prevention
    // ──────────────────────────────────────────────────────────────

    public function test_user_cannot_access_other_org_bank_accounts(): void
    {
        $bankAccountB = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Org B Bank',
            'currency' => 'CHF',
        ]);

        // User A tries to access Org B's bank account
        $response = $this->actingAs($this->userA)
            ->get("/reconciliation/{$bankAccountB->id}");

        $response->assertForbidden();
    }

    public function test_user_cannot_import_to_other_org_bank_account(): void
    {
        $bankAccountB = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Org B Bank',
            'currency' => 'CHF',
        ]);

        $xmlContent = file_get_contents(__DIR__ . '/../fixtures/camt053_sample.xml');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('test.xml', $xmlContent);

        $response = $this->actingAs($this->userA)
            ->post("/reconciliation/{$bankAccountB->id}/import", [
                'camt_file' => $file,
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────
    //  Invoice Isolation in Reconciliation
    // ──────────────────────────────────────────────────────────────

    public function test_invoices_scoped_in_reconciliation_suggestions(): void
    {
        $this->setCurrentOrg($this->orgA);

        $accountBank = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value,
        ]);
        $accountAR = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1100', 'name' => 'AR', 'type' => AccountType::Asset->value,
        ]);

        $bankAccount = BankAccount::create([
            'organization_id' => $this->orgA->id,
            'account_id' => $accountBank->id,
            'name' => 'Main',
            'currency' => 'CHF',
            'balance' => 0,
        ]);

        // Create invoice in Org B (should NOT be suggested)
        $clientB = Customer::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Client B',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'customer_id' => $clientB->id,
            'number' => 'INV-B-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 1000.00,
            'vat_amount' => 0,
            'total' => 1000.00,
            'currency' => 'CHF',
        ]);

        // Create invoice in Org A
        $clientA = Customer::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Client A',
        ]);

        Invoice::create([
            'organization_id' => $this->orgA->id,
            'customer_id' => $clientA->id,
            'number' => 'INV-A-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 1000.00,
            'vat_amount' => 0,
            'total' => 1000.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Payment',
            'amount' => 1000.00,
            'type' => BankTransactionType::Credit,
        ]);

        $reconciliationService = app(\App\Domains\Banking\Services\ReconciliationService::class);
        $suggestions = $reconciliationService->generateSuggestions($transaction);

        // Should only suggest Org A's invoice
        $invoiceNumbers = $suggestions['invoices']->pluck('number')->toArray();
        $this->assertContains('INV-A-001', $invoiceNumbers);
        $this->assertNotContains('INV-B-001', $invoiceNumbers);
    }

    // ──────────────────────────────────────────────────────────────
    //  Auto-assign Organization ID
    // ──────────────────────────────────────────────────────────────

    public function test_bank_import_auto_assigns_organization_id(): void
    {
        $this->setCurrentOrg($this->orgA);

        $bankAccount = BankAccount::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Test Bank',
            'currency' => 'CHF',
        ]);

        $import = BankImport::create([
            'bank_account_id' => $bankAccount->id,
            'filename' => 'test.xml',
            'format' => 'camt053',
        ]);

        // Organization ID should be auto-assigned by BelongsToOrganization trait
        $this->assertEquals($this->orgA->id, $import->organization_id);
    }
}
