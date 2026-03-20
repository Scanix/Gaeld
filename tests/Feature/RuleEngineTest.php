<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Rules\QrReferencePaymentRule;
use App\Domains\Banking\Rules\RecurringEntryRule;
use App\Domains\Banking\Rules\SupplierCategoryRule;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Banking\Services\RuleEngineService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleEngineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private BankAccount $bankAccount;

    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->organization = Organization::create(['name' => 'Rule Test Org', 'currency' => 'CHF']);
        $this->organization->users()->attach($user->id, ['role' => 'owner']);

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
            'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'name' => 'CHF Konto',
            'iban' => 'CH56 0483 5012 3456 7800 9',
            'currency' => 'CHF',
            'account_id' => $this->accounts['bank']->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  QrReferencePaymentRule
    // ──────────────────────────────────────────────────────────────

    public function test_qr_rule_matches_credit_with_structured_reference(): void
    {
        $client = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Test Client']);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $client->id,
            'number' => 'INV-2026-001',
            'status' => InvoiceStatus::Sent->value,
            'issue_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'total' => 1000.00,
            'subtotal' => 1000.00,
            'currency' => 'CHF',
            'qr_reference' => '000000000000000012345678901',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'Payment for invoice',
            'amount' => 1000.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => '000000000000000012345678901',
            'import_hash' => 'hash-qr-test',
            'is_reconciled' => false,
        ]);

        $rule = new QrReferencePaymentRule(app(ReconciliationService::class));

        $this->assertTrue($rule->matches($transaction));
        $this->assertEquals(100, $rule->confidence());
    }

    public function test_qr_rule_does_not_match_debit_transaction(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'Expense payment',
            'amount' => -500.00,
            'type' => BankTransactionType::Debit,
            'structured_reference' => '000000000000000099999999',
            'import_hash' => 'hash-debit-test',
            'is_reconciled' => false,
        ]);

        $rule = new QrReferencePaymentRule(app(ReconciliationService::class));

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_qr_rule_does_not_match_without_reference(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'No reference payment',
            'amount' => 500.00,
            'type' => BankTransactionType::Credit,
            'structured_reference' => null,
            'import_hash' => 'hash-no-ref',
            'is_reconciled' => false,
        ]);

        $rule = new QrReferencePaymentRule(app(ReconciliationService::class));

        $this->assertFalse($rule->matches($transaction));
    }

    // ──────────────────────────────────────────────────────────────
    //  SupplierCategoryRule
    // ──────────────────────────────────────────────────────────────

    public function test_supplier_rule_confidence_is_90(): void
    {
        $rule = new SupplierCategoryRule();
        $this->assertEquals(90, $rule->confidence());
    }

    public function test_supplier_rule_does_not_match_credit_transaction(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'Income',
            'amount' => 100.00,
            'type' => BankTransactionType::Credit,
            'import_hash' => 'hash-supplier-credit',
            'is_reconciled' => false,
        ]);

        $rule = new SupplierCategoryRule();
        $this->assertFalse($rule->matches($transaction));
    }

    // ──────────────────────────────────────────────────────────────
    //  RecurringEntryRule
    // ──────────────────────────────────────────────────────────────

    public function test_recurring_rule_confidence_is_70(): void
    {
        $rule = new RecurringEntryRule();
        $this->assertEquals(70, $rule->confidence());
    }

    public function test_recurring_rule_matches_repeated_amount(): void
    {
        // Create two past reconciled transactions with same amount
        for ($i = 1; $i <= 2; $i++) {
            BankTransaction::create([
                'bank_account_id' => $this->bankAccount->id,
                'bank_import_id' => null,
                'date' => now()->subMonths($i)->toDateString(),
                'description' => 'Monthly SaaS subscription',
                'amount' => -99.00,
                'type' => BankTransactionType::Debit,
                'import_hash' => "hash-recurring-past-{$i}",
                'is_reconciled' => true,
            ]);
        }

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => now()->toDateString(),
            'description' => 'Monthly SaaS subscription',
            'amount' => -99.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-recurring-current',
            'is_reconciled' => false,
        ]);

        $rule = new RecurringEntryRule();
        $this->assertTrue($rule->matches($transaction));
    }

    // ──────────────────────────────────────────────────────────────
    //  RuleEngineService — Feature Flag Enforcement
    // ──────────────────────────────────────────────────────────────

    public function test_rule_engine_throws_in_ce_without_flag(): void
    {
        config(['features.rule_engine' => false]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'Test',
            'amount' => 100.00,
            'type' => BankTransactionType::Credit,
            'import_hash' => 'hash-ce-flag',
            'is_reconciled' => false,
        ]);

        $ruleEngine = app(RuleEngineService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Enterprise Edition');

        $ruleEngine->evaluateRules($transaction);
    }

    public function test_rule_engine_runs_when_flag_enabled(): void
    {
        config(['features.rule_engine' => true]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'Test payment',
            'amount' => 50.00,
            'type' => BankTransactionType::Credit,
            'import_hash' => 'hash-ee-enabled',
            'is_reconciled' => false,
        ]);

        $ruleEngine = app(RuleEngineService::class);

        // Should not throw — returns an empty collection (no rules match)
        $results = $ruleEngine->evaluateRules($transaction);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }

    public function test_rule_engine_skip_reconciled_transactions(): void
    {
        config(['features.rule_engine' => true]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'bank_import_id' => null,
            'date' => '2026-03-15',
            'description' => 'Already reconciled',
            'amount' => 100.00,
            'type' => BankTransactionType::Credit,
            'import_hash' => 'hash-reconciled',
            'is_reconciled' => true,
        ]);

        $ruleEngine = app(RuleEngineService::class);
        $results = $ruleEngine->evaluateRules($transaction);

        $this->assertTrue($results->isEmpty());
    }
}
