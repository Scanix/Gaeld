<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;

class YearEndClosingActionTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['currency' => 'CHF']);

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

        $income = [
            ['account_id' => $revenue->id, 'balance' => '15000.00', 'code' => '3000'],
            ['account_id' => $serviceRevenue->id, 'balance' => '5000.00', 'code' => '3200'],
        ];

        $expenses = [
            ['account_id' => $cogs->id, 'balance' => '8000.00', 'code' => '4000'],
            ['account_id' => $generalExp->id, 'balance' => '3000.00', 'code' => '6000'],
        ];

        $action = app(YearEndClosingAction::class);
        $action->execute($orgId, 2025, $income, $expenses, $resultAccount, '2025-12-31', 'YE-2025');

        // Verify closing journal entry was created
        $closingEntry = JournalEntry::where('reference', 'YE-2025')->first();
        $this->assertNotNull($closingEntry);
        $this->assertTrue($closingEntry->is_posted);
        $this->assertSame('2025-12-31', $closingEntry->date->toDateString());

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

        $resultAccount = Account::where('organization_id', $orgId)->where('code', '2900')->first();
        $revenue = Account::where('organization_id', $orgId)->where('code', '3000')->first();

        $income = [
            ['account_id' => $revenue->id, 'balance' => '5000.00', 'code' => '3000'],
        ];

        // Zero-balance expense — should be skipped
        $expenses = [
            ['account_id' => Account::where('organization_id', $orgId)->where('code', '4000')->first()->id, 'balance' => '0', 'code' => '4000'],
        ];

        $action = app(YearEndClosingAction::class);
        $action->execute($orgId, 2025, $income, $expenses, $resultAccount, '2025-12-31', 'YE-ZERO');

        $closingEntry = JournalEntry::where('reference', 'YE-ZERO')->first();
        $this->assertNotNull($closingEntry);

        // Should have only 2 lines: revenue debit + result credit (zero expense skipped)
        $this->assertCount(2, $closingEntry->lines);
    }
}
