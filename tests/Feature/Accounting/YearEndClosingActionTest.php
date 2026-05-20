<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
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
}
