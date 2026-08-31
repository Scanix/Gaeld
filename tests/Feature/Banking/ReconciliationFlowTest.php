<?php

namespace Tests\Feature\Banking;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Enums\CamtFormat;
use App\Domains\Banking\Exceptions\ReconciliationFailedException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Queries\BankAccountQuery;
use App\Domains\Banking\Services\BankImportService;
use App\Domains\Banking\Services\MatchingService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Banking\Services\SuggestionService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ReconciliationFlowTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private BankAccount $bankAccount;

    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

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
        $this->accounts['vat_payable'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '2201', 'name' => 'VAT Payable', 'type' => AccountType::Liability->value,
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

    public function test_bank_account_select_cache_can_be_refreshed_after_account_creation(): void
    {
        BankAccountQuery::forgetSelectCache($this->organization->id);

        $this->assertCount(1, BankAccountQuery::forSelect());

        BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->accounts['bank']->id,
            'name' => 'Second Account',
            'iban' => 'CH5604835012345678009',
            'currency' => 'CHF',
            'is_active' => true,
        ]);

        BankAccountQuery::forgetSelectCache($this->organization->id);

        $this->assertCount(2, BankAccountQuery::forSelect());
    }

    public function test_import_camt053_creates_transactions(): void
    {
        $importService = app(BankImportService::class);
        $xml = file_get_contents(__DIR__.'/../../fixtures/camt053_sample.xml');

        $import = $importService->importCamtFile($this->bankAccount, $xml, 'test.xml');

        $this->assertEquals(CamtFormat::Camt053, $import->format);
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
        $xml = file_get_contents(__DIR__.'/../../fixtures/camt054_sample.xml');

        $import = $importService->importCamtFile($this->bankAccount, $xml, 'notification.xml');

        $this->assertEquals(CamtFormat::Camt054, $import->format);
        $this->assertEquals('NOTIF-2026-001', $import->statement_id);
        $this->assertEquals(2, $import->transaction_count);
    }

    public function test_duplicate_import_skips_existing_transactions(): void
    {
        $importService = app(BankImportService::class);
        $xml = file_get_contents(__DIR__.'/../../fixtures/camt053_sample.xml');

        $import1 = $importService->importCamtFile($this->bankAccount, $xml, 'test1.xml');
        $import2 = $importService->importCamtFile($this->bankAccount, $xml, 'test2.xml');

        $this->assertEquals(3, $import1->transaction_count);
        $this->assertEquals(0, $import2->transaction_count); // All duplicates

        // Total transactions should still be 3
        $this->assertEquals(3, BankTransaction::where('bank_account_id', $this->bankAccount->id)->count());
    }

    public function test_matching_is_idempotent_when_a_confirmed_candidate_is_reprocessed(): void
    {
        $customer = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Recurring Customer AG',
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-MATCH-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'currency' => 'CHF',
        ]);
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-10',
            'description' => 'Payment for INV-MATCH-001',
            'amount' => '100.00',
            'type' => BankTransactionType::Credit,
            'reference' => 'INV-MATCH-001',
            'debtor_name' => 'Recurring Customer AG',
        ]);

        $service = app(MatchingService::class);
        $matches = $service->findAndStoreMatches($transaction);
        $matches->firstOrFail()->update(['is_confirmed' => true]);

        $service->findAndStoreMatches($transaction->fresh());

        $this->assertSame(1, BankMatch::where('bank_transaction_id', $transaction->id)->count());
        $this->assertDatabaseHas('bank_matches', [
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'is_confirmed' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Manual Reconciliation Tests
    // ──────────────────────────────────────────────────────────────

    public function test_reconcile_transaction_with_invoice(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Contact::create([
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
            'status' => ExpenseStatus::Posted,
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

    public function test_reconcile_transaction_with_vat_payment(): void
    {
        $journalEntry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-03-31',
            'reference' => 'VAT-SETTLEMENT-2026-Q1',
            'description' => 'VAT settlement',
            'is_posted' => true,
            'type' => 'vat_settlement',
        ]);
        TransactionLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->accounts['bank']->id,
            'debit' => 100.00,
            'credit' => 0,
        ]);
        TransactionLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->accounts['vat_payable']->id,
            'debit' => 0,
            'credit' => 100.00,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-10',
            'description' => 'VAT payment to AFC',
            'amount' => 100.00,
            'type' => BankTransactionType::Debit,
            'reference' => 'VAT-PAYMENT-001',
        ]);

        $result = app(ReconciliationService::class)->reconcileWithVatSettlement($transaction, $journalEntry);

        $this->assertTrue($result->is_reconciled);
        $this->assertSame($journalEntry->id, $result->vat_settlement_journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());
        $this->assertSame(
            $this->accounts['vat_payable']->id,
            $result->journalEntry->lines->first(fn ($line) => $line->debit === '100.00')->account_id,
        );
    }

    public function test_vat_payment_with_an_excess_amount_is_rejected(): void
    {
        $journalEntry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-03-31',
            'reference' => 'VAT-SETTLEMENT-2026-Q1',
            'description' => 'VAT settlement',
            'is_posted' => true,
            'type' => 'vat_settlement',
        ]);
        TransactionLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->accounts['bank']->id,
            'debit' => 100.00,
            'credit' => 0,
        ]);
        TransactionLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->accounts['vat_payable']->id,
            'debit' => 0,
            'credit' => 100.00,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-10',
            'description' => 'VAT payment to AFC',
            'amount' => 100.01,
            'type' => BankTransactionType::Debit,
            'reference' => 'VAT-PAYMENT-002',
        ]);

        $this->expectException(ReconciliationFailedException::class);
        app(ReconciliationService::class)->reconcileWithVatSettlement($transaction, $journalEntry);
    }

    public function test_vat_route_hides_a_foreign_transaction(): void
    {
        $otherOrganization = Organization::create(['name' => 'Other VAT Org', 'currency' => 'CHF']);
        $otherAccount = Account::create([
            'organization_id' => $otherOrganization->id,
            'code' => '1020',
            'name' => 'Other bank',
            'type' => AccountType::Asset->value,
        ]);
        $otherBankAccount = BankAccount::create([
            'organization_id' => $otherOrganization->id,
            'account_id' => $otherAccount->id,
            'name' => 'Other account',
            'iban' => 'CH9300762011623852957',
            'bank_name' => 'Other bank',
            'currency' => 'CHF',
            'balance' => 0,
        ]);
        $transaction = BankTransaction::create([
            'bank_account_id' => $otherBankAccount->id,
            'date' => '2026-04-10',
            'description' => 'Foreign VAT payment',
            'amount' => 100.00,
            'type' => BankTransactionType::Debit,
            'reference' => 'VAT-FOREIGN-001',
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post(route('reconciliation.vat', $transaction), [
                'vat_settlement_id' => 'missing-settlement-id',
            ])
            ->assertNotFound();
    }

    public function test_reconcile_vat_expense_uses_net_amount_for_ledger_and_gross_for_bank(): void
    {
        $vatAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1170',
            'name' => 'Input VAT',
            'type' => AccountType::Asset->value,
        ]);
        $vatRate = VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);
        $expense = Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'description' => 'VAT subscription',
            'amount' => '120.00',
            'vat_amount' => '9.72',
            'vat_rate_id' => $vatRate->id,
            'date' => '2026-03-12',
            'vendor' => 'Software vendor',
            'status' => ExpenseStatus::Posted,
            'currency' => 'CHF',
            'payment_method' => 'bank_transfer',
        ]);
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-12',
            'description' => 'VAT subscription payment',
            'amount' => '129.72',
            'type' => BankTransactionType::Debit,
            'reference' => 'EXP-VAT',
        ]);

        $result = app(ReconciliationService::class)->reconcileWithExpense($transaction, $expense, '6530');
        $lines = $result->journalEntry->lines;

        $this->assertSame('120.00', $lines->firstWhere('account_id', $this->accounts['software']->id)->debit);
        $this->assertSame('9.72', $lines->firstWhere('account_id', $vatAccount->id)->debit);
        $this->assertSame('129.72', $lines->firstWhere('account_id', $this->accounts['bank']->id)->credit);
        $this->assertSame('9870.28', $result->bankAccount->balance);
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

        $client = Contact::create([
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

        $result = $reconciliationService->reconcileWithContraAccount($transaction, '3000');

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

        $client = Contact::create([
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
        $this->assertEquals('INV-MATCH', $suggestions['invoices']->first()->invoice->number);
    }

    public function test_suggestions_match_by_reference(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Contact::create([
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
        $this->assertEquals('INV-REF-TEST', $suggestions['invoices']->first()->invoice->number);
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
        $this->expectExceptionMessage('Feature [auto_reconciliation] is not enabled.');

        $reconciliationService->autoReconcile($this->bankAccount);
    }

    public function test_auto_reconcile_works_when_enabled(): void
    {
        config(['features.auto_reconciliation' => true]);

        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        // Create a high-confidence match
        $client = Contact::create([
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
        $response = $this->actingAs($this->user)->get("/reconciliation/{$this->bankAccount->uuid}");

        $response->assertStatus(200);
    }

    public function test_reconciliation_show_exposes_the_frontend_contract_for_empty_state(): void
    {
        $this->actingAs($this->user)
            ->get("/reconciliation/{$this->bankAccount->uuid}")
            ->assertInertia(fn ($page) => $page
                ->component('Banking/ReconciliationShow')
                ->has('bankAccount')
                ->has('transactions.data', 0)
                ->has('suggestions')
                ->has('personalSuggestions')
                ->where('filter', 'unreconciled')
                ->has('openInvoices')
                ->has('openExpenses')
                ->has('pageFeatures.auto_reconciliation'));
    }

    public function test_auto_reconcile_route_blocked_in_ce(): void
    {
        config(['features.auto_reconciliation' => false]);

        $response = $this->actingAs($this->user)
            ->post("/reconciliation/{$this->bankAccount->uuid}/auto");

        $response->assertForbidden();
    }

    public function test_camt_upload_route(): void
    {
        $xmlContent = file_get_contents(__DIR__.'/../../fixtures/camt053_sample.xml');
        $file = UploadedFile::fake()->createWithContent('test.xml', $xmlContent);

        $response = $this->actingAs($this->user)
            ->post("/reconciliation/{$this->bankAccount->uuid}/import", [
                'camt_file' => $file,
            ]);

        $response->assertRedirect();
        $this->assertEquals(3, BankTransaction::where('bank_account_id', $this->bankAccount->id)->count());
    }

    public function test_camt_upload_uses_generic_error_when_import_exception_message_is_empty(): void
    {
        $xmlContent = file_get_contents(__DIR__.'/../../fixtures/camt053_sample.xml');
        $file = UploadedFile::fake()->createWithContent('test.xml', $xmlContent);

        $this->mock(BankImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('importCamtFile')
                ->once()
                ->andThrow(new \RuntimeException(''));
        });

        $response = $this->actingAs($this->user)
            ->from("/reconciliation/{$this->bankAccount->uuid}")
            ->post("/reconciliation/{$this->bankAccount->uuid}/import", [
                'camt_file' => $file,
            ]);

        $response->assertRedirect("/reconciliation/{$this->bankAccount->uuid}");
        $response->assertSessionHas('error', __('app.unexpected_error'));
    }
}
