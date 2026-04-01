<?php

namespace Tests\Feature\Banking;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Models\PersonalTransactionPattern;
use App\Domains\Banking\Services\PersonalPatternService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class PersonalReconciliationTest extends TestCase
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
            'name' => 'Freelancer Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->organization, 'owner');

        app(CurrentOrganization::class)->set($this->organization);

        $this->accounts['bank'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020', 'name' => 'Bank Account CHF', 'type' => AccountType::Asset->value,
        ]);
        $this->accounts['private'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => AccountCode::PRIVATE_WITHDRAWALS,
            'name' => 'Private Contributions and Withdrawals',
            'type' => AccountType::Equity->value,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->accounts['bank']->id,
            'name' => 'Personal & Business Account',
            'iban' => 'CH56 0483 5012 3456 7800 9',
            'bank_name' => 'PostFinance',
            'currency' => 'CHF',
            'balance' => 5000.00,
            'is_mixed_use' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  ReconcileAsPersonal – Service Tests
    // ──────────────────────────────────────────────────────────────

    public function test_reconcile_debit_as_personal_posts_to_2850(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Migros grocery shopping',
            'amount' => -150.00,
            'type' => BankTransactionType::Debit,
            'creditor_name' => 'Migros',
            'import_hash' => 'hash-personal-debit',
            'is_reconciled' => false,
        ]);

        $service = app(ReconciliationService::class);
        $result = $service->reconcileAsPersonal($transaction);

        $this->assertTrue($result->is_reconciled);
        $this->assertTrue($result->is_personal);
        $this->assertNotNull($result->journal_entry_id);
        $this->assertTrue($result->journalEntry->isBalanced());

        // Debit 2850 / Credit Bank
        $lines = $result->journalEntry->lines;
        $debitLine = $lines->first(fn ($l) => $l->debit > 0);
        $creditLine = $lines->first(fn ($l) => $l->credit > 0);

        $this->assertEquals($this->accounts['private']->id, $debitLine->account_id);
        $this->assertEquals($this->accounts['bank']->id, $creditLine->account_id);
        $this->assertEquals('150.00', $debitLine->debit);
        $this->assertEquals('150.00', $creditLine->credit);

        // Balance should decrease by 150 (only once)
        $this->assertEquals('4850.00', $result->bankAccount->balance);
    }

    public function test_reconcile_credit_as_personal_posts_to_2850(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-20',
            'description' => 'Personal salary transfer',
            'amount' => 3000.00,
            'type' => BankTransactionType::Credit,
            'debtor_name' => 'Employer SA',
            'import_hash' => 'hash-personal-credit',
            'is_reconciled' => false,
        ]);

        $service = app(ReconciliationService::class);
        $result = $service->reconcileAsPersonal($transaction);

        $this->assertTrue($result->is_reconciled);
        $this->assertTrue($result->is_personal);

        // Debit Bank / Credit 2850
        $lines = $result->journalEntry->lines;
        $debitLine = $lines->first(fn ($l) => $l->debit > 0);
        $creditLine = $lines->first(fn ($l) => $l->credit > 0);

        $this->assertEquals($this->accounts['bank']->id, $debitLine->account_id);
        $this->assertEquals($this->accounts['private']->id, $creditLine->account_id);

        // Balance should increase by 3000
        $this->assertEquals('8000.00', $result->bankAccount->balance);
    }

    public function test_reconcile_personal_records_counterparty_pattern(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Coop supermarket',
            'amount' => -75.50,
            'type' => BankTransactionType::Debit,
            'creditor_name' => 'Coop',
            'import_hash' => 'hash-pattern-test',
            'is_reconciled' => false,
        ]);

        $service = app(ReconciliationService::class);
        $service->reconcileAsPersonal($transaction);

        $pattern = PersonalTransactionPattern::where('organization_id', $this->organization->id)
            ->where('counterparty_name', 'coop')
            ->first();

        $this->assertNotNull($pattern);
        $this->assertEquals(1, $pattern->hit_count);
    }

    public function test_reconcile_personal_fails_if_already_reconciled(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Already reconciled',
            'amount' => -100.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-already-reconciled',
            'is_reconciled' => true,
        ]);

        $service = app(ReconciliationService::class);

        $this->expectException(\App\Domains\Banking\Exceptions\AlreadyReconciledException::class);
        $service->reconcileAsPersonal($transaction);
    }

    // ──────────────────────────────────────────────────────────────
    //  Bulk Reconciliation
    // ──────────────────────────────────────────────────────────────

    public function test_bulk_reconcile_personal_processes_multiple(): void
    {
        $transactions = collect();

        for ($i = 1; $i <= 3; $i++) {
            $transactions->push(BankTransaction::create([
                'bank_account_id' => $this->bankAccount->id,
                'date' => "2026-03-0{$i}",
                'description' => "Bulk personal {$i}",
                'amount' => -50.00,
                'type' => BankTransactionType::Debit,
                'creditor_name' => "Vendor {$i}",
                'import_hash' => "hash-bulk-{$i}",
                'is_reconciled' => false,
            ]));
        }

        $service = app(ReconciliationService::class);
        $result = $service->bulkReconcileAsPersonal($transactions);

        $this->assertEquals(3, $result['reconciled']);
        $this->assertEquals(0, $result['skipped']);

        foreach ($transactions as $tx) {
            $this->assertTrue($tx->fresh()->is_personal);
            $this->assertTrue($tx->fresh()->is_reconciled);
        }
    }

    public function test_bulk_reconcile_skips_already_reconciled(): void
    {
        $good = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-01',
            'description' => 'Not reconciled',
            'amount' => -50.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-bulk-good',
            'is_reconciled' => false,
        ]);

        $already = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-02',
            'description' => 'Already reconciled',
            'amount' => -50.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-bulk-already',
            'is_reconciled' => true,
        ]);

        $service = app(ReconciliationService::class);
        $result = $service->bulkReconcileAsPersonal(collect([$good, $already]));

        $this->assertEquals(1, $result['reconciled']);
        $this->assertEquals(1, $result['skipped']);
    }

    // ──────────────────────────────────────────────────────────────
    //  PersonalPatternService
    // ──────────────────────────────────────────────────────────────

    public function test_pattern_service_learns_and_suggests_after_threshold(): void
    {
        $service = app(PersonalPatternService::class);

        $tx1 = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-01',
            'description' => 'Migros',
            'amount' => -45.00,
            'type' => BankTransactionType::Debit,
            'creditor_name' => 'Migros Branch A',
            'import_hash' => 'hash-learn-1',
            'is_reconciled' => false,
        ]);

        $tx2 = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Migros',
            'amount' => -60.00,
            'type' => BankTransactionType::Debit,
            'creditor_name' => 'Migros Branch A',
            'import_hash' => 'hash-learn-2',
            'is_reconciled' => false,
        ]);

        // First hit — below threshold
        $service->recordPersonalTransaction($tx1, $this->organization->id);
        $this->assertFalse($service->isLikelyPersonal($tx2, $this->organization->id));

        // Second hit — now at threshold (≥2)
        $service->recordPersonalTransaction($tx2, $this->organization->id);

        $tx3 = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-25',
            'description' => 'Migros',
            'amount' => -30.00,
            'type' => BankTransactionType::Debit,
            'creditor_name' => 'Migros Branch A',
            'import_hash' => 'hash-learn-3',
            'is_reconciled' => false,
        ]);

        $this->assertTrue($service->isLikelyPersonal($tx3, $this->organization->id));
    }

    public function test_pattern_service_returns_personal_counterparties(): void
    {
        PersonalTransactionPattern::create([
            'organization_id' => $this->organization->id,
            'counterparty_name' => 'migros',
            'hit_count' => 3,
            'last_seen_at' => now(),
        ]);

        PersonalTransactionPattern::create([
            'organization_id' => $this->organization->id,
            'counterparty_name' => 'coop',
            'hit_count' => 1, // Below threshold
            'last_seen_at' => now(),
        ]);

        $service = app(PersonalPatternService::class);
        $counterparties = $service->getPersonalCounterparties($this->organization->id);

        $this->assertCount(1, $counterparties);
        $this->assertContains('migros', $counterparties->toArray());
        $this->assertNotContains('coop', $counterparties->toArray());
    }

    public function test_pattern_service_ignores_null_counterparty(): void
    {
        $service = app(PersonalPatternService::class);

        $tx = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-01',
            'description' => 'Mystery transaction',
            'amount' => -20.00,
            'type' => BankTransactionType::Debit,
            'creditor_name' => null,
            'debtor_name' => null,
            'import_hash' => 'hash-null-counterparty',
            'is_reconciled' => false,
        ]);

        $service->recordPersonalTransaction($tx, $this->organization->id);

        $this->assertEquals(0, PersonalTransactionPattern::count());
        $this->assertFalse($service->isLikelyPersonal($tx, $this->organization->id));
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP Endpoint Tests
    // ──────────────────────────────────────────────────────────────

    public function test_http_reconcile_personal_requires_mixed_use(): void
    {
        $normalAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->accounts['bank']->id,
            'name' => 'Business Only',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'currency' => 'CHF',
            'is_mixed_use' => false,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $normalAccount->id,
            'date' => '2026-03-15',
            'description' => 'Some payment',
            'amount' => -100.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-non-mixed',
            'is_reconciled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('reconciliation.personal', $transaction));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse($transaction->fresh()->is_reconciled);
    }

    public function test_http_reconcile_personal_marks_transaction(): void
    {
        $transaction = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-15',
            'description' => 'Migros groceries',
            'amount' => -85.00,
            'type' => BankTransactionType::Debit,
            'creditor_name' => 'Migros',
            'import_hash' => 'hash-http-personal',
            'is_reconciled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('reconciliation.personal', $transaction));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertTrue($transaction->is_reconciled);
        $this->assertTrue($transaction->is_personal);
    }

    public function test_http_bulk_reconcile_personal(): void
    {
        $tx1 = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-01',
            'description' => 'Bulk 1',
            'amount' => -30.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-http-bulk-1',
            'is_reconciled' => false,
        ]);

        $tx2 = BankTransaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'date' => '2026-03-02',
            'description' => 'Bulk 2',
            'amount' => -40.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-http-bulk-2',
            'is_reconciled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('reconciliation.bulk-personal', $this->bankAccount), [
                'transaction_ids' => [$tx1->id, $tx2->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($tx1->fresh()->is_personal);
        $this->assertTrue($tx2->fresh()->is_personal);
    }

    public function test_http_bulk_reconcile_personal_requires_mixed_use(): void
    {
        $normalAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->accounts['bank']->id,
            'name' => 'Pure Business',
            'iban' => 'CH93 0076 2011 6238 5295 7',
            'currency' => 'CHF',
            'is_mixed_use' => false,
        ]);

        $tx = BankTransaction::create([
            'bank_account_id' => $normalAccount->id,
            'date' => '2026-03-01',
            'description' => 'Test',
            'amount' => -50.00,
            'type' => BankTransactionType::Debit,
            'import_hash' => 'hash-bulk-non-mixed',
            'is_reconciled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('reconciliation.bulk-personal', $normalAccount), [
                'transaction_ids' => [$tx->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse($tx->fresh()->is_reconciled);
    }
}
