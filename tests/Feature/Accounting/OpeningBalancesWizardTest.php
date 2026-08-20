<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class OpeningBalancesWizardTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Account $bank;

    private Account $debtors;

    private Account $creditors;

    private Account $opening;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->bank = $this->makeAccount('1020', 'Bank', AccountType::Asset);
        $this->debtors = $this->makeAccount('1100', 'Debtors', AccountType::Asset);
        $this->creditors = $this->makeAccount('2000', 'Creditors', AccountType::Liability);
        $this->opening = $this->makeAccount(AccountCode::OPENING_BALANCE, 'Opening Balance', AccountType::Equity);
        // P&L accounts should NOT appear in the wizard
        $this->makeAccount('3000', 'Revenue', AccountType::Revenue);
        $this->makeAccount('4000', 'Expense', AccountType::Expense);
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

    public function test_wizard_only_shows_balance_sheet_accounts(): void
    {
        $response = $this->actAsOrg()->get('/accounting/opening-balances');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/OpeningBalances')
            // 3 balance-sheet accounts (bank, debtors, creditors); 9000 + P&L are excluded
            ->has('accounts', 3));
    }

    public function test_store_records_balanced_opening_entry_with_contra(): void
    {
        $response = $this->actAsOrg()->post('/accounting/opening-balances', [
            'date' => '2026-01-01',
            'balances' => [
                ['account_id' => $this->bank->id, 'amount' => '10000.00'],
                ['account_id' => $this->debtors->id, 'amount' => '2500.00'],
                ['account_id' => $this->creditors->id, 'amount' => '4000.00'],
            ],
        ]);

        $response->assertRedirect('/accounting/journal-entries');

        $entry = JournalEntry::where('reference', 'OPENING-2026')->with('lines')->firstOrFail();
        $this->assertTrue($entry->is_posted);
        $this->assertSame('2026-01-01', $entry->date->toDateString());

        // 3 entered + 1 contra
        $this->assertCount(4, $entry->lines);

        $totalDebit = $entry->lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $entry->lines->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001);

        // bank (asset, positive) -> debit 10000
        $bankLine = $entry->lines->firstWhere('account_id', $this->bank->id);
        $this->assertSame('10000.00', $bankLine->debit);
        $this->assertSame('0.00', $bankLine->credit);

        // creditors (liability, positive) -> credit 4000
        $credLine = $entry->lines->firstWhere('account_id', $this->creditors->id);
        $this->assertSame('0.00', $credLine->debit);
        $this->assertSame('4000.00', $credLine->credit);

        // contra on 9000 plugs the diff (10000+2500 = 12500 debit vs 4000 credit -> 8500 credit on 9000)
        $contra = $entry->lines->firstWhere('account_id', $this->opening->id);
        $this->assertNotNull($contra);
        $this->assertSame('0.00', $contra->debit);
        $this->assertSame('8500.00', $contra->credit);
    }

    public function test_store_skips_empty_rows_and_errors_when_all_zero(): void
    {
        $response = $this->actAsOrg()->from('/accounting/opening-balances')->post('/accounting/opening-balances', [
            'date' => '2026-01-01',
            'balances' => [
                ['account_id' => $this->bank->id, 'amount' => '0'],
            ],
        ]);

        $response->assertRedirect('/accounting/opening-balances');
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('journal_entries', ['reference' => 'OPENING-2026']);
    }

    public function test_store_negative_amount_flips_to_other_side(): void
    {
        // negative amount on an asset = a credit balance for that asset
        $this->actAsOrg()->post('/accounting/opening-balances', [
            'date' => '2026-01-01',
            'balances' => [
                ['account_id' => $this->bank->id, 'amount' => '-500.00'],
            ],
        ]);

        $entry = JournalEntry::where('reference', 'OPENING-2026')->with('lines')->firstOrFail();
        $bankLine = $entry->lines->firstWhere('account_id', $this->bank->id);
        $this->assertSame('0.00', $bankLine->debit);
        $this->assertSame('500.00', $bankLine->credit);
    }

    public function test_store_as_draft_creates_unposted_entry(): void
    {
        $response = $this->actAsOrg()->post('/accounting/opening-balances', [
            'date' => '2026-01-01',
            'is_posted' => false,
            'balances' => [
                ['account_id' => $this->bank->id, 'amount' => '10000.00'],
                ['account_id' => $this->creditors->id, 'amount' => '4000.00'],
            ],
        ]);

        $response->assertRedirect('/accounting/journal-entries');
        $response->assertSessionHas('success');

        $entry = JournalEntry::where('reference', 'OPENING-2026')->with('lines')->firstOrFail();
        $this->assertFalse($entry->is_posted);
        $this->assertSame('2026-01-01', $entry->date->toDateString());

        // 2 entered + 1 contra
        $this->assertCount(3, $entry->lines);
    }
}
