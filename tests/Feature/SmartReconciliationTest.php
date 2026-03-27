<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankImportService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Banking\Services\SuggestionService;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class SmartReconciliationTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $organization;

    private User $user;

    private BankAccount $bankAccount;

    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Test Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->organization, 'owner');

        app(CurrentOrganization::class)->set($this->organization);

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
    //  CAMT Parser: Structured Reference Extraction
    // ──────────────────────────────────────────────────────────────

    public function test_camt053_extracts_structured_qr_reference(): void
    {
        $importService = app(BankImportService::class);
        $xml = file_get_contents(__DIR__.'/../fixtures/camt053_qr_sample.xml');

        $import = $importService->importCamtFile($this->bankAccount, $xml, 'qr_test.xml');

        $this->assertEquals(3, $import->transaction_count);

        $transactions = $import->transactions->sortBy('date')->values();

        // Entry 1: Structured reference from RmtInf/Strd/CdtrRefInf/Ref
        $this->assertEquals('210000000003139471430009017', $transactions[0]->structured_reference);

        // Entry 2: QR reference extracted from unstructured text
        $this->assertEquals('000000000000000000000000026', $transactions[1]->structured_reference);

        // Entry 3: No QR reference
        $this->assertNull($transactions[2]->structured_reference);
    }

    // ──────────────────────────────────────────────────────────────
    //  Matching Engine: QR Reference Match (confidence = 100)
    // ──────────────────────────────────────────────────────────────

    public function test_match_by_qr_reference_returns_confidence_100(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Helvetia GmbH',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-QR-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 1500.00,
            'vat_amount' => 0,
            'total' => 1500.00,
            'currency' => 'CHF',
            'qr_reference' => '210000000003139471430009017',
            'qr_type' => 'QRR',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-01',
            'description' => 'Payment from Helvetia GmbH',
            'amount' => 1500.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '210000000003139471430009017',
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        $this->assertCount(1, $suggestions['invoices']);
        $this->assertEquals(100, $suggestions['invoices']->first()->score);
        $this->assertEquals('qr_reference', $suggestions['invoices']->first()->matchType->value);
        $this->assertEquals($invoice->id, $suggestions['invoices']->first()->invoice->id);

        // Verify match is stored in bank_matches
        $this->assertDatabaseHas('bank_matches', [
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'confidence' => 100,
            'match_type' => 'qr_reference',
            'is_confirmed' => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Matching Engine: Amount + Client Match (confidence = 90)
    // ──────────────────────────────────────────────────────────────

    public function test_match_by_amount_and_client_returns_confidence_90(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Alpine Solutions',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-AMOUNT-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 2500.00,
            'vat_amount' => 0,
            'total' => 2500.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-02',
            'description' => 'Wire transfer',
            'amount' => 2500.00,
            'type' => BankTransactionType::Credit,
            'debtor_name' => 'Alpine Solutions AG',
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        $this->assertNotEmpty($suggestions['invoices']);
        $bestMatch = $suggestions['invoices']->first();
        $this->assertEquals(90, $bestMatch->score);
        $this->assertEquals('amount_customer', $bestMatch->matchType->value);
    }

    // ──────────────────────────────────────────────────────────────
    //  Matching Engine: Heuristic Match (confidence = 70)
    // ──────────────────────────────────────────────────────────────

    public function test_match_by_heuristic_returns_confidence_70(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Unknown Corp',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-2026-099',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 750.00,
            'vat_amount' => 0,
            'total' => 750.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-03',
            'description' => 'Payment',
            'amount' => 750.00,
            'type' => BankTransactionType::Credit,
            'debtor_name' => 'Someone Else',
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        $this->assertNotEmpty($suggestions['invoices']);
        $bestMatch = $suggestions['invoices']->first();
        $this->assertEquals(70, $bestMatch->score);
        $this->assertEquals('heuristic', $bestMatch->matchType->value);
    }

    // ──────────────────────────────────────────────────────────────
    //  Matching Engine: QR Match Priority
    // ──────────────────────────────────────────────────────────────

    public function test_qr_match_takes_priority_over_amount_match(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Helvetia GmbH',
        ]);

        // Invoice with QR reference (should be picked via QR match)
        $qrInvoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-QR-PRIO',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 1000.00,
            'vat_amount' => 0,
            'total' => 1000.00,
            'currency' => 'CHF',
            'qr_reference' => '999000000003139471430009017',
        ]);

        // Another invoice with same amount (should NOT match when QR matches)
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-SAME-AMOUNT',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 1000.00,
            'vat_amount' => 0,
            'total' => 1000.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-01',
            'description' => 'Payment from Helvetia GmbH',
            'amount' => 1000.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '999000000003139471430009017',
            'debtor_name' => 'Helvetia GmbH',
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        // Only the QR match should be returned (QR returns early)
        $this->assertCount(1, $suggestions['invoices']);
        $this->assertEquals($qrInvoice->id, $suggestions['invoices']->first()->invoice->id);
        $this->assertEquals(100, $suggestions['invoices']->first()->score);
    }

    // ──────────────────────────────────────────────────────────────
    //  Confirm Match: Record Payment + Reconcile
    // ──────────────────────────────────────────────────────────────

    public function test_confirm_match_records_payment_and_reconciles(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Confirm Corp',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-CONFIRM-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 3000.00,
            'vat_amount' => 0,
            'total' => 3000.00,
            'currency' => 'CHF',
            'qr_reference' => '888000000003139471430009017',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-05',
            'description' => 'QR payment from Confirm Corp',
            'amount' => 3000.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '888000000003139471430009017',
        ]);

        // Create the match
        $suggestions = $suggestionService->generateSuggestions($transaction);
        $match = BankMatch::where('bank_transaction_id', $transaction->id)
            ->where('confidence', 100)
            ->first();

        $this->assertNotNull($match);

        // Confirm the match
        $result = $reconciliationService->confirmMatch($match);

        // Transaction should be reconciled
        $this->assertTrue($result->is_reconciled);
        $this->assertEquals($invoice->id, $result->matched_invoice_id);
        $this->assertNotNull($result->journal_entry_id);

        // Match should be marked as confirmed
        $match->refresh();
        $this->assertTrue($match->is_confirmed);
        $this->assertNotNull($match->confirmed_at);

        // Invoice should have a payment recorded
        $invoice->refresh();
        $this->assertTrue(bccomp($invoice->amountPaid(), '3000.00', 2) === 0);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    // ──────────────────────────────────────────────────────────────
    //  Safety: Duplicate Payment Prevention
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_confirm_match_for_already_reconciled_transaction(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Dup Corp',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-DUP-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 500.00,
            'vat_amount' => 0,
            'total' => 500.00,
            'currency' => 'CHF',
            'qr_reference' => '777000000003139471430009017',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-06',
            'description' => 'Payment',
            'amount' => 500.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '777000000003139471430009017',
        ]);

        // Create and confirm match
        $suggestionService->generateSuggestions($transaction);
        $match = BankMatch::where('bank_transaction_id', $transaction->id)->first();
        $reconciliationService->confirmMatch($match);

        // Create a second transaction attempting same invoice
        $transaction2 = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-07',
            'description' => 'Duplicate payment attempt',
            'amount' => 500.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '777000000003139471430009017',
        ]);

        // The invoice is now PAID, so no match should be found
        $suggestions = $suggestionService->generateSuggestions($transaction2);
        $this->assertEmpty($suggestions['invoices']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Auto Reconciliation (EE): QR Match Auto-Confirm
    // ──────────────────────────────────────────────────────────────

    public function test_auto_reconcile_confirms_exact_qr_matches(): void
    {
        config(['features.auto_reconciliation' => true]);

        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Auto Corp',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-AUTO-QR-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 4200.00,
            'vat_amount' => 0,
            'total' => 4200.00,
            'currency' => 'CHF',
            'qr_reference' => '666000000003139471430009017',
        ]);

        BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-07',
            'description' => 'QR payment',
            'amount' => 4200.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '666000000003139471430009017',
        ]);

        $result = $reconciliationService->autoReconcile($this->bankAccount);

        $this->assertEquals(1, $result['matched']);
        $this->assertEquals(0, $result['unmatched']);

        // Invoice should be paid
        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);

        // Match should be confirmed
        $this->assertDatabaseHas('bank_matches', [
            'invoice_id' => $invoice->id,
            'confidence' => 100,
            'is_confirmed' => true,
        ]);
    }

    public function test_auto_reconcile_does_not_confirm_non_exact_matches(): void
    {
        config(['features.auto_reconciliation' => true]);

        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'NoAutoConfirm Corp',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-NO-AUTO',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 1234.56,
            'vat_amount' => 0,
            'total' => 1234.56,
            'currency' => 'CHF',
        ]);

        BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-08',
            'description' => 'Payment',
            'amount' => 1234.56,
            'type' => BankTransactionType::Credit,
            'debtor_name' => 'NoAutoConfirm Corp',
        ]);

        $result = $reconciliationService->autoReconcile($this->bankAccount);

        // Should NOT auto-confirm because confidence is 90 (amount+client), not 100
        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(1, $result['unmatched']);

        // Match should exist but not be confirmed
        $this->assertDatabaseHas('bank_matches', [
            'confidence' => 90,
            'is_confirmed' => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Debit Transactions Don't Match Invoices
    // ──────────────────────────────────────────────────────────────

    public function test_debit_transactions_do_not_match_invoices(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $suggestionService = app(SuggestionService::class);

        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Client',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-DEBIT-TEST',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 100.00,
            'vat_amount' => 0,
            'total' => 100.00,
            'currency' => 'CHF',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-01',
            'description' => 'Debit payment',
            'amount' => 100.00,
            'type' => BankTransactionType::Debit,
        ]);

        $suggestions = $suggestionService->generateSuggestions($transaction);

        $this->assertEmpty($suggestions['invoices']);
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP: Confirm Match Route
    // ──────────────────────────────────────────────────────────────

    public function test_confirm_match_route(): void
    {
        $client = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Route Test Corp',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-ROUTE-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'subtotal' => 800.00,
            'vat_amount' => 0,
            'total' => 800.00,
            'currency' => 'CHF',
            'qr_reference' => '555000000003139471430009017',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-04-09',
            'description' => 'Payment',
            'amount' => 800.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '555000000003139471430009017',
        ]);

        $match = BankMatch::create([
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $invoice->id,
            'confidence' => 100,
            'match_type' => 'qr_reference',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/reconciliation/matches/{$match->id}/confirm");

        $response->assertRedirect();

        $transaction->refresh();
        $this->assertTrue($transaction->is_reconciled);
        $this->assertEquals($invoice->id, $transaction->matched_invoice_id);
    }
}
