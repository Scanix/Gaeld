<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;

class YearEndClosingActionTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['currency' => 'CHF']);
        $this->user = User::factory()->create();

        // Standard chart
        $this->createAccount('1020', 'Bank', AccountType::Asset);
        $this->createAccount('2800', 'Equity', AccountType::Equity);
        $this->createAccount('2900', 'Annual Result', AccountType::Equity);
        $this->createAccount('3000', 'Revenue', AccountType::Revenue);
        $this->createAccount('3200', 'Service Revenue', AccountType::Revenue);
        $this->createAccount('4000', 'Cost of Goods', AccountType::Expense);
        $this->createAccount('6000', 'General Expenses', AccountType::Expense);
        $this->createAccount(AccountCode::OPENING_BALANCE, 'Opening Balance', AccountType::Equity);
    }

    private function createAccount(string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code' => $code,
            'name' => $name,
            'type' => $type->value,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(int $year = 2025, string $reference = 'YE-2025'): array
    {
        return [
            'year' => $year,
            'fiscal_year_id' => null,
            'closing_date' => "{$year}-12-31",
            'reference' => $reference,
            'result_account_code' => '2900',
        ];
    }

    public function test_closing_transfers_pl_balances_to_result_account(): void
    {
        $orgId = $this->organization->id;

        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();
        $revenue = Account::where('organization_id', $orgId)->where('code', '3000')->first();
        $serviceRevenue = Account::where('organization_id', $orgId)->where('code', '3200')->first();
        $cogs = Account::where('organization_id', $orgId)->where('code', '4000')->first();
        $generalExp = Account::where('organization_id', $orgId)->where('code', '6000')->first();
        $resultAccount = Account::where('organization_id', $orgId)->where('code', '2900')->first();

        // Post revenue: 15000 + 5000 = 20000
        $this->postJournalEntry('2025-03-01', [
            $this->journalLine($bank, '15000.00', '0', 'Client payment'),
            $this->journalLine($revenue, '0', '15000.00', 'Sales'),
        ], 'JE-R1');

        $this->postJournalEntry('2025-06-01', [
            $this->journalLine($bank, '5000.00', '0', 'Service payment'),
            $this->journalLine($serviceRevenue, '0', '5000.00', 'Consulting'),
        ], 'JE-R2');

        // Post expenses: 8000 + 3000 = 11000
        $this->postJournalEntry('2025-04-01', [
            $this->journalLine($cogs, '8000.00', '0', 'Materials'),
            $this->journalLine($bank, '0', '8000.00', 'Payment'),
        ], 'JE-E1');

        $this->postJournalEntry('2025-09-01', [
            $this->journalLine($generalExp, '3000.00', '0', 'Office'),
            $this->journalLine($bank, '0', '3000.00', 'Payment'),
        ], 'JE-E2');

        $action = app(YearEndClosingAction::class);
        $action->execute($this->organization, $this->validated(), $this->user);

        // Verify closing journal entry was created
        $closingEntry = JournalEntry::where('reference', 'YE-2025')->first();
        $this->assertNotNull($closingEntry);
        $this->assertTrue($closingEntry->is_posted);
        $this->assertSame('2025-12-31', $closingEntry->date->toDateString());
        $this->assertSame('year_end_closing', $closingEntry->type);

        // Verify the entry is balanced
        $totalDebit = $closingEntry->lines->sum('debit');
        $totalCredit = $closingEntry->lines->sum('credit');
        $this->assertSame(0, bccomp((string) $totalDebit, (string) $totalCredit, 2));

        // Result account should reflect net income: 20000 - 11000 = 9000
        $resultLine = $closingEntry->lines->where('account_id', $resultAccount->id)->first();
        $this->assertNotNull($resultLine);
        // Net income positive → result account has more credit (income) than debit (expense)
        $netCredit = bcsub((string) $resultLine->credit, (string) $resultLine->debit, 2);
        $this->assertSame(0, bccomp($netCredit, '9000.00', 2), 'Net income should be 9000');

        // Fiscal year should be closed
        $this->organization->refresh();
        $this->assertTrue($this->organization->isFiscalYearClosed(2025));
    }

    public function test_closing_skips_zero_balance_accounts(): void
    {
        $orgId = $this->organization->id;

        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();
        $revenue = Account::where('organization_id', $orgId)->where('code', '3000')->first();
        $cogs = Account::where('organization_id', $orgId)->where('code', '4000')->first();

        // Post revenue only
        $this->postJournalEntry('2025-06-01', [
            $this->journalLine($bank, '5000.00', '0', 'Client payment'),
            $this->journalLine($revenue, '0', '5000.00', 'Sales'),
        ], 'JE-R1');

        // Post and fully reverse an expense so it nets to zero — should be skipped
        $this->postJournalEntry('2025-04-01', [
            $this->journalLine($cogs, '1000.00', '0', 'Goods'),
            $this->journalLine($bank, '0', '1000.00', 'Payment'),
        ], 'JE-E1');

        $this->postJournalEntry('2025-04-15', [
            $this->journalLine($cogs, '0', '1000.00', 'Reversal'),
            $this->journalLine($bank, '1000.00', '0', 'Reversal'),
        ], 'JE-E2');

        $action = app(YearEndClosingAction::class);
        $action->execute($this->organization, $this->validated(reference: 'YE-ZERO'), $this->user);

        $closingEntry = JournalEntry::where('reference', 'YE-ZERO')->first();
        $this->assertNotNull($closingEntry);

        // Should have only 2 lines: revenue debit + result credit (zero-balance expense skipped)
        $this->assertCount(2, $closingEntry->lines);
    }

    public function test_closing_throws_when_no_accounts_to_close(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No accounts to close for this period.');

        $action = app(YearEndClosingAction::class);
        $action->execute($this->organization, $this->validated(), $this->user);
    }

    public function test_closing_throws_when_result_account_not_found(): void
    {
        $orgId = $this->organization->id;
        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();
        $revenue = Account::where('organization_id', $orgId)->where('code', '3000')->first();

        $this->postJournalEntry('2025-06-01', [
            $this->journalLine($bank, '5000.00', '0', 'Client payment'),
            $this->journalLine($revenue, '0', '5000.00', 'Sales'),
        ], 'JE-R1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Account 'NONEXISTENT' not found.");

        $validated = $this->validated();
        $validated['result_account_code'] = 'NONEXISTENT';

        $action = app(YearEndClosingAction::class);
        $action->execute($this->organization, $validated, $this->user);
    }

    public function test_long_fiscal_year_blocks_on_an_unsettled_overlapping_vat_period(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);

        $bank = Account::where('organization_id', $this->organization->id)->where('code', '1020')->first();
        $revenue = Account::where('organization_id', $this->organization->id)->where('code', '3000')->first();
        $this->postJournalEntry('2025-05-01', [
            $this->journalLine($bank, '100.00', '0', 'VAT activity'),
            $this->journalLine($revenue, '0', '100.00', 'VAT activity'),
        ], 'VAT-Q2-2025');
        $entry = JournalEntry::where('organization_id', $this->organization->id)
            ->where('reference', 'VAT-Q2-2025')
            ->firstOrFail();

        VatEntry::create([
            'journal_entry_id' => $entry->id,
            'vat_rate_id' => VatRate::factory()->for($this->organization)->create()->id,
            'base_amount' => '100.00',
            'vat_amount' => '8.10',
            'type' => VatEntryType::Output,
        ]);

        $validated = $this->validated(year: 2024, reference: 'YE-LONG-VAT');
        $validated['fiscal_year_id'] = $fiscalYear->id;
        $validated['closing_date'] = '2025-06-30';

        try {
            app(YearEndClosingAction::class)->execute($this->organization, $validated, $this->user);
            $this->fail('Expected the unsettled VAT period to block closing.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Q2 2025', $exception->getMessage());
        }

        $this->assertDatabaseMissing('journal_entries', [
            'organization_id' => $this->organization->id,
            'reference' => 'YE-LONG-VAT',
        ]);
        $this->assertSame(FiscalYearStatus::Operative, $fiscalYear->refresh()->status);
    }

    public function test_long_fiscal_year_uses_selected_fiscal_year_when_legacy_year_is_closed(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);

        $this->organization->closeFiscalYear(2025);

        $bank = Account::where('organization_id', $this->organization->id)->where('code', '1020')->first();
        $revenue = Account::where('organization_id', $this->organization->id)->where('code', '3000')->first();
        $this->postJournalEntry('2025-05-01', [
            $this->journalLine($bank, '100.00', '0', 'Long fiscal year revenue'),
            $this->journalLine($revenue, '0', '100.00', 'Long fiscal year revenue'),
        ], 'YE-LONG-REVENUE');

        $validated = $this->validated(year: 2024, reference: 'YE-LONG-SELECTED');
        $validated['fiscal_year_id'] = $fiscalYear->id;
        $validated['closing_date'] = '2025-06-30';

        app(YearEndClosingAction::class)->execute($this->organization, $validated, $this->user);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->organization->id,
            'reference' => 'YE-LONG-SELECTED',
            'type' => 'year_end_closing',
        ]);
        $this->assertSame(FiscalYearStatus::Closed, $fiscalYear->refresh()->status);
    }

    public function test_closing_rolls_back_entirely_when_opening_balances_step_fails(): void
    {
        $orgId = $this->organization->id;

        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();
        $revenue = Account::where('organization_id', $orgId)->where('code', '3000')->first();

        $this->postJournalEntry('2025-06-01', [
            $this->journalLine($bank, '5000.00', '0', 'Client payment'),
            $this->journalLine($revenue, '0', '5000.00', 'Sales'),
        ], 'JE-R1');

        // Pre-occupy the reference that GenerateOpeningBalancesAction will try
        // to use for the next year ("OPENING-2026"), simulating the scenario
        // where closing is re-run (e.g. after a reopen) and the opening
        // balances step collides with an entry already posted by an earlier
        // attempt. This must make the ENTIRE closing operation roll back —
        // not just leave the opening-balances step incomplete.
        $this->postJournalEntry('2026-01-01', [
            $this->journalLine($bank, '1.00', '0', 'Pre-existing wash'),
            $this->journalLine($revenue, '0', '1.00', 'Pre-existing wash'),
        ], 'OPENING-2026');

        $action = app(YearEndClosingAction::class);

        try {
            $action->execute($this->organization, $this->validated(reference: 'YE-2025'), $this->user);
            $this->fail('Expected DuplicateReferenceException to be thrown.');
        } catch (DuplicateReferenceException $e) {
            // expected
        }

        // The closing journal entry must NOT have been committed.
        $this->assertNull(JournalEntry::where('organization_id', $orgId)->where('reference', 'YE-2025')->first());

        // The fiscal year must NOT be marked closed.
        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2025));
    }
}
