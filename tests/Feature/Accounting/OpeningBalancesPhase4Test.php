<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ClosingAccountsService;
use App\Domains\Accounting\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class OpeningBalancesPhase4Test extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Account $equity;

    private Account $opening;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->opening = $this->makeAccount(AccountCode::OPENING_BALANCE, 'Opening Balance', AccountType::Equity);
        $this->equity = $this->makeAccount('2800', 'Retained Earnings', AccountType::Equity);
        $this->bank = $this->makeAccount('1020', 'Bank', AccountType::Asset);
    }

    private function makeAccount(string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code' => $code,
            'name' => $name,
            'type' => $type->value,
            'is_active' => true,
        ]);
    }

    // ── Step 4.1: scenario guide props ──────────────────────────────

    public function test_opening_balances_index_returns_equity_accounts_and_existing_historical(): void
    {
        $response = $this->actAsOrg()->get('/accounting/opening-balances');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/OpeningBalances')
            ->has('equityAccounts')
            ->has('existingHistorical'));
    }

    public function test_equity_accounts_excludes_opening_balance_account(): void
    {
        $response = $this->actAsOrg()->get('/accounting/opening-balances');

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $accounts = $page->toArray()['props']['equityAccounts'];
            $codes = array_column($accounts, 'code');
            $this->assertNotContains(AccountCode::OPENING_BALANCE, $codes);
            $this->assertContains('2800', $codes);
        });
    }

    public function test_existing_historical_is_null_when_none_recorded(): void
    {
        $response = $this->actAsOrg()->get('/accounting/opening-balances');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('existingHistorical', null));
    }

    public function test_existing_historical_is_returned_when_present(): void
    {
        $entry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2024-12-31',
            'reference' => 'HIST-2024',
            'is_posted' => true,
            'type' => 'historical_summary',
        ]);

        $response = $this->actAsOrg()->get('/accounting/opening-balances');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('existingHistorical.reference', 'HIST-2024'));
    }

    // ── Step 4.2: storeHistorical ──────────────────────────────────

    public function test_store_historical_creates_posted_journal_entry_with_type(): void
    {
        $response = $this->actAsOrg()->post('/accounting/opening-balances/historical', [
            'date' => '2024-12-31',
            'account_id' => $this->equity->id,
            'amount' => '15000.00',
        ]);

        $response->assertRedirect('/accounting/opening-balances');

        $entry = JournalEntry::where('type', 'historical_summary')->with('lines')->firstOrFail();
        $this->assertTrue($entry->is_posted);
        $this->assertSame('historical_summary', $entry->type);
        $this->assertSame('2024-12-31', $entry->date->toDateString());

        // Must be balanced
        $totalDebit = $entry->lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $entry->lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);

        // Profit: equity account gets credit, opening balance (9000) gets debit
        $equityLine = $entry->lines->firstWhere('account_id', $this->equity->id);
        $this->assertNotNull($equityLine);
        $this->assertSame('0.00', $equityLine->debit);
        $this->assertSame('15000.00', $equityLine->credit);

        $openingLine = $entry->lines->firstWhere('account_id', $this->opening->id);
        $this->assertNotNull($openingLine);
        $this->assertSame('15000.00', $openingLine->debit);
        $this->assertSame('0.00', $openingLine->credit);
    }

    public function test_store_historical_net_loss_debits_equity_account(): void
    {
        $this->actAsOrg()->post('/accounting/opening-balances/historical', [
            'date' => '2024-12-31',
            'account_id' => $this->equity->id,
            'amount' => '-5000.00',
        ]);

        $entry = JournalEntry::where('type', 'historical_summary')->with('lines')->firstOrFail();

        // Loss: equity account gets debit, opening balance (9000) gets credit
        $equityLine = $entry->lines->firstWhere('account_id', $this->equity->id);
        $this->assertSame('5000.00', $equityLine->debit);
        $this->assertSame('0.00', $equityLine->credit);

        $openingLine = $entry->lines->firstWhere('account_id', $this->opening->id);
        $this->assertSame('0.00', $openingLine->debit);
        $this->assertSame('5000.00', $openingLine->credit);
    }

    public function test_store_historical_uses_auto_reference_when_none_given(): void
    {
        $this->actAsOrg()->post('/accounting/opening-balances/historical', [
            'date' => '2024-12-31',
            'account_id' => $this->equity->id,
            'amount' => '1000.00',
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'HIST-2024',
            'type' => 'historical_summary',
        ]);
    }

    public function test_store_historical_validates_zero_amount(): void
    {
        $response = $this->actAsOrg()->from('/accounting/opening-balances')
            ->post('/accounting/opening-balances/historical', [
                'date' => '2024-12-31',
                'account_id' => $this->equity->id,
                'amount' => '0',
            ]);

        $response->assertRedirect('/accounting/opening-balances');
        $response->assertSessionHasErrors('amount');
    }

    public function test_store_historical_validates_required_fields(): void
    {
        $response = $this->actAsOrg()->from('/accounting/opening-balances')
            ->post('/accounting/opening-balances/historical', []);

        $response->assertRedirect('/accounting/opening-balances');
        $response->assertSessionHasErrors(['date', 'account_id', 'amount']);
    }

    // ── Step 4.2: ClosingAccountsService excludes historical_summary ─

    public function test_closing_accounts_service_excludes_historical_summary_entries(): void
    {
        // Create a revenue account and an expense account
        $revenue = $this->makeAccount('3000', 'Revenue', AccountType::Revenue);
        $expense = $this->makeAccount('6000', 'Expense', AccountType::Expense);

        $ledger = app(LedgerService::class);

        // Normal posted entry within the period
        $normalEntry = $ledger->postEntry($this->organization->id, new JournalEntryData(
            date: '2025-06-01',
            reference: 'INV-001',
            description: null,
            lines: [
                new JournalLineData(accountId: (string) $this->bank->id, debit: '1000', credit: '0'),
                new JournalLineData(accountId: (string) $revenue->id, debit: '0', credit: '1000'),
            ],
        ));

        // Historical summary entry — should be excluded from closing calculation
        $histEntry = $ledger->postEntry($this->organization->id, new JournalEntryData(
            date: '2025-01-01',
            reference: 'HIST-2024',
            description: null,
            lines: [
                new JournalLineData(accountId: (string) $this->opening->id, debit: '500', credit: '0'),
                new JournalLineData(accountId: (string) $revenue->id, debit: '0', credit: '500'),
            ],
        ));
        $histEntry->update(['type' => 'historical_summary']);

        $service = app(ClosingAccountsService::class);
        [$income, $expenses, $net] = $service->compute($this->organization->id, '2025-01-01', '2025-12-31');

        // Only the normal INV-001 entry should count (1000 credit on revenue)
        // The historical 500 credit should be excluded
        $revenueRow = collect($income)->firstWhere('account_id', $revenue->id);
        $this->assertNotNull($revenueRow);
        $this->assertSame('1000.00', $revenueRow['balance']);
    }
}
