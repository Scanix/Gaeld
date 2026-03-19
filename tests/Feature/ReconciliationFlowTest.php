<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankImportService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Banking\Services\SuggestionService;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationFlowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private BankAccount $bankAccount;

    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);

        // Bind current organization
        app()->instance('current_organization', $this->organization);

        $this->accounts['bank'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020', 'name' => 'Bank Account CHF', 'type' => AccountType::Asset->value,
        ]);
        $this->accounts['ar'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value,
        ]);
        $this->accounts['revenue'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000', 'name' => 'Revenue from Services', 'type' => AccountType::Revenue->value,
        ]);
        $this->accounts['software'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '6530', 'name' => 'Software and Subscriptions', 'type' => AccountType::Expense->value,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->accounts['bank']->id,
            'name' => 'Main Account',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'bank_name' => 'UBS',
            'currency' => 'CHF',
            'balance' => 10000.00,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  CAMT Import Tests
    // ──────────────────────────────────────────────────────────────

    public function test_import_camt053_creates_transactions(): void
    {
        $importService = app(BankImportService::class);
        $xml = file_get_contents(__DIR__ . '/../fixtures/camt053_sample.xml');

        $import = $importService->importCamtFile($this->bankAccount, $xml, 'test.xml');

        $this->assertEquals('camt053', $import->format);
        $this->assertEquals('STMT-2026-001', $import->statement_id);
        $this->assertEquals(3, $import->transaction_count);
        $this->assertCount(3, $import->transactions);

        // Verify transactions are not reconciled
        foreach ($import->transactions as $tx) {
            $this->assertFalse($tx->is_reconciled);
            $this->assertEquals($this->bankAccount->id, $tx->bank_account_id);
        }
    }

    public function test_import_camt054_creates_transactions(): void
    {
        $importService = app(BankImportService::class);
        $xml = file_get_contents(__DIR__ . '/../fixtures/camt054_sample.xml');

        $import = $importService->importCamtFile($this->bankAccount, $xml, 'notification.xml');

        $this->assertEquals('camt054', $import->format);
        $this->assertEquals('NOTIF-2026-001', $import->statement_id);
        $this->assertEquals(2, $import->transaction_count);
    }

    public function test_duplicate_import_skips_existing_transactions(): void
    {
        $importService = app(BankImportService::class);
        $xml = file_get_contents(__DIR__ . '/../fixtures/camt053_sample.xml');

        $import1 = $importService->importCamtFile($this->bankAccount, $xml, 'test1.xml');
        $import2 = $importService->importCamtFile($this->bankAccount, $xml, 'test2.xml');

        $this->assertEquals(3, $import1->transaction_count);
        $this->assertEquals(0, $import2->transaction_count); // All duplicates

        // Total transactions should still be 3
        $this->assertEquals(3, BankTransaction::where('bank_account_id', $this->bankAccount->id)->count());
    }

    // ──────────────────────────────────────────────────────────────
    //  Manual Reconciliation Tests
    // ──────────────────────────────────────────────────────────────

    public function test_reconcile_transaction_with_invoice(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme AG',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-2026-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 5000.00,
            'vat_amount' => 0,
            'total' => 5000.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Payment from Acme AG',
            'amount' => 5000.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-2026-001',
        ]);

        $result = $reconciliationService->reconcileWithInvoice($transaction, $invoice);

        $this->assertTrue($result->is_reconciled);
        $this->assertNotNull($result->journal_entry_id);
        $this->assertEquals($invoice->id, $result->matched_invoice_id);
        $this->assertTrue($result->journalEntry->isBalanced());

        // Bank balance should be updated
        $this->assertEquals('15000.00', $result->bankAccount->balance);
    }

    public function test_reconcile_transaction_with_expense(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'description' => 'GitHub Pro',
            'amount' => 200.00,
            'vat_amount' => 0,
            'date' => '2026-03-12',
            'vendor' => 'GitHub',
            'status' => ExpenseStatus::Approved,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-12',
            'description' => 'GitHub Pro subscription',
            'amount' => 200.00,
            'type' => BankTransactionType::Debit,
            'reference' => 'EXP-GITHUB',
        ]);

        $result = $reconciliationService->reconcileWithExpense($transaction, $expense, '6530');

        $this->assertTrue($result->is_reconciled);
        $this->assertNotNull($result->journal_entry_id);
        $this->assertEquals($expense->id, $result->matched_expense_id);
        $this->assertTrue($result->journalEntry->isBalanced());

        // Bank balance should decrease
        $this->assertEquals('9800.00', $result->bankAccount->balance);
    }

    public function test_cannot_reconcile_already_reconciled_transaction(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Already reconciled',
            'amount' => 100.00,
            'type' => BankTransactionType::Credit,
            'is_reconciled' => true,
        ]);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Client',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-TEST',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 100.00,
            'vat_amount' => 0,
            'total' => 100.00,
            'currency' => 'CHF',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already reconciled');

        $reconciliationService->reconcileWithInvoice($transaction, $invoice);
    }

    public function test_manual_reconcile_with_contra_account(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-14',
            'description' => 'Misc income',
            'amount' => 1500.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'MISC-001',
        ]);

        $result = $reconciliationService->reconcileManual($transaction, '3000');

        $this->assertTrue($result->is_reconciled);
        $this->assertNotNull($result->journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());
    }

    // ──────────────────────────────────────────────────────────────
    //  Suggestion Tests
    // ──────────────────────────────────────────────────────────────

    public function test_suggestions_match_by_amount(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme AG',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-MATCH',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 2500.00,
            'vat_amount' => 0,
            'total' => 2500.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Wire transfer',
            'amount' => 2500.00,
            'type' => BankTransactionType::Credit,
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        $this->assertNotEmpty($suggestions['invoices']);
        $this->assertEquals('INV-MATCH', $suggestions['invoices']->first()->number);
    }

    public function test_suggestions_match_by_reference(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Swiss Corp',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-REF-TEST',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 999.00,
            'vat_amount' => 0,
            'total' => 999.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Payment',
            'amount' => 500.00, // Different amount
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-REF-TEST', // Matching reference
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        $this->assertNotEmpty($suggestions['invoices']);
        $this->assertEquals('INV-REF-TEST', $suggestions['invoices']->first()->number);
    }

    // ──────────────────────────────────────────────────────────────
    //  EE Feature Flag Tests
    // ──────────────────────────────────────────────────────────────

    public function test_auto_reconcile_blocked_in_ce(): void
    {
        config(['features.auto_reconciliation' => false]);

        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Enterprise Edition');

        $reconciliationService->autoReconcile($this->bankAccount);
    }

    public function test_auto_reconcile_works_when_enabled(): void
    {
        config(['features.auto_reconciliation' => true]);

        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        // Create a high-confidence match
        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme AG',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-AUTO-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => 7500.00,
            'vat_amount' => 0,
            'total' => 7500.00,
            'currency' => 'CHF',
        ]);

        BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Payment from Acme AG',
            'amount' => 7500.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-AUTO-001',
            'debtor_name' => 'Acme AG',
        ]);

        $result = $reconciliationService->autoReconcile($this->bankAccount);

        $this->assertArrayHasKey('matched', $result);
        $this->assertArrayHasKey('unmatched', $result);
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP Route Tests
    // ──────────────────────────────────────────────────────────────

    public function test_reconciliation_page_accessible(): void
    {
        $response = $this->actingAs($this->user)->get('/reconciliation');

        $response->assertStatus(200);
    }

    public function test_reconciliation_show_page_accessible(): void
    {
        $response = $this->actingAs($this->user)->get("/reconciliation/{$this->bankAccount->id}");

        $response->assertStatus(200);
    }

    public function test_auto_reconcile_route_blocked_in_ce(): void
    {
        config(['features.auto_reconciliation' => false]);

        $response = $this->actingAs($this->user)
            ->post("/reconciliation/{$this->bankAccount->id}/auto");

        $response->assertForbidden();
    }

    public function test_camt_upload_route(): void
    {
        $xmlContent = file_get_contents(__DIR__ . '/../fixtures/camt053_sample.xml');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('test.xml', $xmlContent);

        $response = $this->actingAs($this->user)
            ->post("/reconciliation/{$this->bankAccount->id}/import", [
                'camt_file' => $file,
            ]);

        $response->assertRedirect();
        $this->assertEquals(3, BankTransaction::where('bank_account_id', $this->bankAccount->id)->count());
    }
}
