<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\LettrageLot;
use App\Domains\Accounting\Models\TransactionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;
use Tests\Traits\WithAuthenticatedOrganization;

class LettrageTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase, WithAuthenticatedOrganization;

    private Account $bankAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->bankAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank Account CHF',
            'type' => AccountType::Asset->value,
        ]);

        $this->revenueAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $this->expenseAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '4000',
            'name' => 'Expenses',
            'type' => AccountType::Expense->value,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Index — page rendering
    // ──────────────────────────────────────────────────────────────

    public function test_lettrage_index_shows_account_picker_when_no_account_selected(): void
    {
        $response = $this->actAsOrg()->get('/accounting/account-matching');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Lettrage')
            ->has('accounts', 3)
            ->where('account', null)
            ->where('openItems', [])
        );
    }

    public function test_lettrage_index_returns_open_items_with_journal_entry_data(): void
    {
        $this->postJournalEntry('2026-02-15', [
            $this->journalLine($this->bankAccount, '1000.00', '0.00', 'Payment received'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00', 'Sales revenue'),
        ], 'INV-001', 'Invoice payment');

        $this->postJournalEntry('2026-03-01', [
            $this->journalLine($this->expenseAccount, '500.00', '0.00', 'Office supplies'),
            $this->journalLine($this->bankAccount, '0.00', '500.00', 'Bank withdrawal'),
        ], 'EXP-042', 'Expense payment');

        $response = $this->actAsOrg()->get('/accounting/account-matching?account='.$this->bankAccount->id);

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Lettrage')
            ->where('account.id', $this->bankAccount->id)
            ->where('account.code', '1020')
            ->has('openItems', 2)
            // Verify journal entry relationship is loaded with date & reference
            ->where('openItems.0.journal_entry.date', '2026-02-15T00:00:00.000000Z')
            ->where('openItems.0.journal_entry.reference', 'INV-001')
            ->where('openItems.0.debit', '1000.00')
            ->where('openItems.0.description', 'Payment received')
            ->where('openItems.1.journal_entry.date', '2026-03-01T00:00:00.000000Z')
            ->where('openItems.1.journal_entry.reference', 'EXP-042')
            ->where('openItems.1.credit', '500.00')
            ->where('openItems.1.description', 'Bank withdrawal')
        );
    }

    public function test_lettrage_index_filters_open_items_by_date(): void
    {
        $this->postJournalEntry('2026-01-15', [
            $this->journalLine($this->bankAccount, '200.00', '0.00', 'Jan deposit'),
            $this->journalLine($this->revenueAccount, '0.00', '200.00', 'Jan revenue'),
        ], 'JAN-1');

        $this->postJournalEntry('2026-03-20', [
            $this->journalLine($this->bankAccount, '300.00', '0.00', 'Mar deposit'),
            $this->journalLine($this->revenueAccount, '0.00', '300.00', 'Mar revenue'),
        ], 'MAR-1');

        // Filter up to end of February — only Jan entry should appear
        $response = $this->actAsOrg()->get('/accounting/account-matching?account='.$this->bankAccount->id.'&date=2026-02-28');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('openItems', 1)
            ->where('openItems.0.description', 'Jan deposit')
            ->where('filterDate', '2026-02-28')
        );
    }

    public function test_lettrage_index_excludes_already_lettered_lines(): void
    {
        $this->postJournalEntry('2026-02-01', [
            $this->journalLine($this->bankAccount, '750.00', '0.00', 'Deposit A'),
            $this->journalLine($this->revenueAccount, '0.00', '750.00', 'Revenue A'),
        ], 'DEP-A');

        $this->postJournalEntry('2026-02-10', [
            $this->journalLine($this->expenseAccount, '750.00', '0.00', 'Expense B'),
            $this->journalLine($this->bankAccount, '0.00', '750.00', 'Withdrawal B'),
        ], 'WIT-B');

        // Letter the two bank lines
        $bankLines = TransactionLine::where('account_id', $this->bankAccount->id)->pluck('id')->toArray();
        $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $bankLines,
        ]);

        // Now open items should be empty for this account
        $response = $this->actAsOrg()->get('/accounting/account-matching?account='.$this->bankAccount->id);

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('openItems', 0)
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Store — lettering lines
    // ──────────────────────────────────────────────────────────────

    public function test_letter_balanced_lines_creates_lot_and_sets_key(): void
    {
        $this->postJournalEntry('2026-03-01', [
            $this->journalLine($this->bankAccount, '1200.00', '0.00', 'Client payment'),
            $this->journalLine($this->revenueAccount, '0.00', '1200.00', 'Revenue'),
        ], 'PAY-1');

        $this->postJournalEntry('2026-03-15', [
            $this->journalLine($this->expenseAccount, '1200.00', '0.00', 'Supplier payment'),
            $this->journalLine($this->bankAccount, '0.00', '1200.00', 'Bank withdrawal'),
        ], 'PAY-2');

        $bankLines = TransactionLine::where('account_id', $this->bankAccount->id)->pluck('id')->toArray();

        $response = $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $bankLines,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Lot created with letter key
        $lot = LettrageLot::where('account_id', $this->bankAccount->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals('A', $lot->letter_key);
        $this->assertFalse($lot->is_reversed);
        $this->assertCount(2, $lot->line_ids);

        // Lines have the key set
        foreach ($bankLines as $lineId) {
            $this->assertEquals('A', TransactionLine::find($lineId)->lettrage_key);
        }
    }

    public function test_letter_unbalanced_lines_fails(): void
    {
        $this->postJournalEntry('2026-03-01', [
            $this->journalLine($this->bankAccount, '1000.00', '0.00', 'Deposit'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00', 'Revenue'),
        ], 'DEP-1');

        $this->postJournalEntry('2026-03-10', [
            $this->journalLine($this->expenseAccount, '500.00', '0.00', 'Half expense'),
            $this->journalLine($this->bankAccount, '0.00', '500.00', 'Half withdrawal'),
        ], 'WIT-1');

        $bankLines = TransactionLine::where('account_id', $this->bankAccount->id)->pluck('id')->toArray();

        $response = $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $bankLines,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('lettrage');

        // No lot created
        $this->assertEquals(0, LettrageLot::count());
    }

    public function test_letter_requires_at_least_two_lines(): void
    {
        $response = $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => [999],
        ]);

        $response->assertSessionHasErrors('line_ids');
    }

    // ──────────────────────────────────────────────────────────────
    //  Destroy — unlettering
    // ──────────────────────────────────────────────────────────────

    public function test_unletter_reverses_lot_and_clears_keys(): void
    {
        $this->postJournalEntry('2026-03-01', [
            $this->journalLine($this->bankAccount, '800.00', '0.00', 'Deposit'),
            $this->journalLine($this->revenueAccount, '0.00', '800.00', 'Revenue'),
        ], 'LT-1');

        $this->postJournalEntry('2026-03-15', [
            $this->journalLine($this->expenseAccount, '800.00', '0.00', 'Expense'),
            $this->journalLine($this->bankAccount, '0.00', '800.00', 'Withdrawal'),
        ], 'LT-2');

        $bankLines = TransactionLine::where('account_id', $this->bankAccount->id)->pluck('id')->toArray();

        $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $bankLines,
        ]);

        $lot = LettrageLot::where('account_id', $this->bankAccount->id)->first();

        $response = $this->actAsOrg()->delete('/accounting/account-matching/'.$lot->id);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Lot is reversed
        $this->assertTrue($lot->fresh()->is_reversed);

        // Lines have keys cleared — back to open items
        foreach ($bankLines as $lineId) {
            $this->assertNull(TransactionLine::find($lineId)->lettrage_key);
        }
    }

    public function test_unletter_makes_lines_reappear_as_open_items(): void
    {
        $this->postJournalEntry('2026-04-01', [
            $this->journalLine($this->bankAccount, '600.00', '0.00', 'Deposit'),
            $this->journalLine($this->revenueAccount, '0.00', '600.00', 'Revenue'),
        ], 'RE-1');

        $this->postJournalEntry('2026-04-05', [
            $this->journalLine($this->expenseAccount, '600.00', '0.00', 'Expense'),
            $this->journalLine($this->bankAccount, '0.00', '600.00', 'Withdrawal'),
        ], 'RE-2');

        $bankLines = TransactionLine::where('account_id', $this->bankAccount->id)->pluck('id')->toArray();

        $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $bankLines,
        ]);

        $lot = LettrageLot::where('account_id', $this->bankAccount->id)->first();
        $this->actAsOrg()->delete('/accounting/account-matching/'.$lot->id);

        // Open items should show 2 lines again with their journal entry data
        $response = $this->actAsOrg()->get('/accounting/account-matching?account='.$this->bankAccount->id);

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('openItems', 2)
            ->has('openItems.0.journal_entry')
            ->has('openItems.1.journal_entry')
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Letter key sequencing
    // ──────────────────────────────────────────────────────────────

    public function test_successive_letterings_increment_letter_key(): void
    {
        // First pair: A
        $this->postJournalEntry('2026-03-01', [
            $this->journalLine($this->bankAccount, '100.00', '0.00', 'D1'),
            $this->journalLine($this->revenueAccount, '0.00', '100.00', 'R1'),
        ], 'S-1');
        $this->postJournalEntry('2026-03-02', [
            $this->journalLine($this->expenseAccount, '100.00', '0.00', 'E1'),
            $this->journalLine($this->bankAccount, '0.00', '100.00', 'W1'),
        ], 'S-2');

        $lines1 = TransactionLine::where('account_id', $this->bankAccount->id)
            ->whereNull('lettrage_key')->pluck('id')->toArray();
        $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $lines1,
        ]);

        // Second pair: B
        $this->postJournalEntry('2026-03-10', [
            $this->journalLine($this->bankAccount, '200.00', '0.00', 'D2'),
            $this->journalLine($this->revenueAccount, '0.00', '200.00', 'R2'),
        ], 'S-3');
        $this->postJournalEntry('2026-03-11', [
            $this->journalLine($this->expenseAccount, '200.00', '0.00', 'E2'),
            $this->journalLine($this->bankAccount, '0.00', '200.00', 'W2'),
        ], 'S-4');

        $lines2 = TransactionLine::where('account_id', $this->bankAccount->id)
            ->whereNull('lettrage_key')->pluck('id')->toArray();
        $this->actAsOrg()->post('/accounting/account-matching', [
            'account_id' => $this->bankAccount->id,
            'line_ids' => $lines2,
        ]);

        $lots = LettrageLot::where('account_id', $this->bankAccount->id)
            ->where('is_reversed', false)
            ->orderBy('id')
            ->get();

        $this->assertEquals('A', $lots[0]->letter_key);
        $this->assertEquals('B', $lots[1]->letter_key);
    }
}
