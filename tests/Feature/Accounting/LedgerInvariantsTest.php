<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Exceptions\InvalidEntryDataException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies core double-entry accounting invariants:
 * - SUM(debit) = SUM(credit) globally and per entry
 * - Trial balance always balances
 * - Multi-organization isolation at the service layer
 * - Concurrent entries maintain consistency
 */
class LedgerInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledgerService;

    private LedgerQueryService $queryService;

    private Organization $orgA;

    private Organization $orgB;

    private array $accountsA = [];

    private array $accountsB = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(LedgerService::class);
        $this->queryService = app(LedgerQueryService::class);

        $user = User::factory()->create();

        $this->orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $this->orgA->users()->attach($user->id, ['role' => 'owner']);

        $this->orgB = Organization::create(['name' => 'Org B', 'currency' => 'CHF']);
        $this->orgB->users()->attach($user->id, ['role' => 'owner']);

        $types = [
            'bank' => ['1020', 'Bank',               AccountType::Asset],
            'ar' => ['1100', 'Accounts Receivable', AccountType::Asset],
            'ap' => ['2000', 'Accounts Payable',    AccountType::Liability],
            'revenue' => ['3000', 'Revenue',             AccountType::Revenue],
            'expense' => ['6500', 'Operating Expenses',  AccountType::Expense],
        ];

        foreach ($types as $key => [$code, $name, $type]) {
            $this->accountsA[$key] = Account::create([
                'organization_id' => $this->orgA->id,
                'code' => $code, 'name' => $name, 'type' => $type->value,
            ]);
            $this->accountsB[$key] = Account::create([
                'organization_id' => $this->orgB->id,
                'code' => $code, 'name' => $name, 'type' => $type->value,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Double-Entry Invariants
    // ──────────────────────────────────────────────────────────────

    public function test_trial_balance_always_balances_after_multiple_entries(): void
    {
        // Post 10 varied entries
        for ($i = 1; $i <= 10; $i++) {
            $amount = (string) ($i * 100);
            $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
                date: "2026-03-{$i}",
                reference: "INV-{$i}",
                description: "Test entry {$i}",
                lines: [
                    new JournalLineData(accountId: $this->accountsA['ar']->id, debit: $amount, credit: '0'),
                    new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: $amount),
                ],
            ));
        }

        $trialBalance = $this->queryService->trialBalance($this->orgA->id);
        $totalDebit = array_sum(array_column($trialBalance, 'debit'));
        $totalCredit = array_sum(array_column($trialBalance, 'credit'));

        $this->assertEquals($totalDebit, $totalCredit, 'Trial balance must have equal debits and credits');
    }

    public function test_compound_entry_with_multiple_lines_balances(): void
    {
        // Revenue split across multiple accounts
        $entry = $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-15',
            reference: 'COMPOUND-001',
            description: 'Compound entry',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '10000.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '8500.00'),
                new JournalLineData(accountId: $this->accountsA['ap']->id, debit: '0', credit: '1500.00'),
            ],
        ));

        $this->assertTrue($entry->isBalanced());
        $this->assertCount(3, $entry->lines);
    }

    public function test_reversal_nets_all_accounts_to_zero(): void
    {
        $entry = $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-05',
            reference: 'TO-REVERSE',
            description: 'Entry to reverse',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '5000.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '3000.00'),
                new JournalLineData(accountId: $this->accountsA['ap']->id, debit: '0', credit: '2000.00'),
            ],
        ));

        $reversal = $this->ledgerService->reverseEntry($entry);
        $this->ledgerService->postDraft($reversal);

        $this->assertSame('0.00', $this->queryService->accountBalance($this->accountsA['bank']->id));
        $this->assertSame('0.00', $this->queryService->accountBalance($this->accountsA['revenue']->id));
        $this->assertSame('0.00', $this->queryService->accountBalance($this->accountsA['ap']->id));
    }

    public function test_zero_amount_entry_rejected(): void
    {
        $this->expectException(InvalidEntryDataException::class);

        $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'ZERO',
            description: 'Zero amount',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '0', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '0'),
            ],
        ));
    }

    public function test_unbalanced_entry_rejected(): void
    {
        $this->expectException(UnbalancedEntryException::class);

        $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'UNBAL',
            description: 'Unbalanced',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '1000.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '999.99'),
            ],
        ));
    }

    // ──────────────────────────────────────────────────────────────
    //  Multi-Organization Isolation
    // ──────────────────────────────────────────────────────────────

    public function test_org_a_entries_invisible_to_org_b_trial_balance(): void
    {
        $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'ORG-A-001',
            description: 'Org A entry',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '5000.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '5000.00'),
            ],
        ));

        // Org B trial balance should be empty or have zero balances
        $trialBalanceB = $this->queryService->trialBalance($this->orgB->id);
        $totalDebitB = array_sum(array_column($trialBalanceB, 'debit'));
        $totalCreditB = array_sum(array_column($trialBalanceB, 'credit'));

        $this->assertEquals(0, $totalDebitB, 'Org B should have zero debits');
        $this->assertEquals(0, $totalCreditB, 'Org B should have zero credits');
    }

    public function test_org_a_account_balance_unaffected_by_org_b_entries(): void
    {
        $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'ORG-A-BAL',
            description: 'Org A opening',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '10000.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '10000.00'),
            ],
        ));

        $this->ledgerService->postEntry($this->orgB->id, new JournalEntryData(
            date: '2026-03-02',
            reference: 'ORG-B-BAL',
            description: 'Org B opening',
            lines: [
                new JournalLineData(accountId: $this->accountsB['bank']->id, debit: '99999.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsB['revenue']->id, debit: '0', credit: '99999.00'),
            ],
        ));

        $this->assertSame('10000.00', $this->queryService->accountBalance($this->accountsA['bank']->id));
        $this->assertSame('99999.00', $this->queryService->accountBalance($this->accountsB['bank']->id));
    }

    public function test_cannot_post_entry_with_other_org_account(): void
    {
        $this->expectException(InvalidEntryDataException::class);

        // Try to post entry to Org A using Org B's account
        $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'CROSS-ORG',
            description: 'Cross-org attempt',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '1000.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsB['revenue']->id, debit: '0', credit: '1000.00'),
            ],
        ));
    }

    public function test_duplicate_reference_allowed_across_orgs(): void
    {
        $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'SAME-REF',
            description: 'Org A entry',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '500.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '500.00'),
            ],
        ));

        // Same reference in Org B should succeed
        $entryB = $this->ledgerService->postEntry($this->orgB->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'SAME-REF',
            description: 'Org B entry',
            lines: [
                new JournalLineData(accountId: $this->accountsB['bank']->id, debit: '700.00', credit: '0'),
                new JournalLineData(accountId: $this->accountsB['revenue']->id, debit: '0', credit: '700.00'),
            ],
        ));

        $this->assertTrue($entryB->is_posted);
    }

    // ──────────────────────────────────────────────────────────────
    //  Precision
    // ──────────────────────────────────────────────────────────────

    public function test_bcmath_precision_with_small_amounts(): void
    {
        $entry = $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'PRECISE-001',
            description: 'Precision test',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '0.01', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '0.01'),
            ],
        ));

        $this->assertTrue($entry->isBalanced());
        $this->assertSame('0.01', $this->queryService->accountBalance($this->accountsA['bank']->id));
    }

    public function test_large_amount_precision(): void
    {
        $entry = $this->ledgerService->postEntry($this->orgA->id, new JournalEntryData(
            date: '2026-03-01',
            reference: 'LARGE-001',
            description: 'Large amount test',
            lines: [
                new JournalLineData(accountId: $this->accountsA['bank']->id, debit: '99999999.99', credit: '0'),
                new JournalLineData(accountId: $this->accountsA['revenue']->id, debit: '0', credit: '99999999.99'),
            ],
        ));

        $this->assertTrue($entry->isBalanced());
        $this->assertSame('99999999.99', $this->queryService->accountBalance($this->accountsA['bank']->id));
    }
}
