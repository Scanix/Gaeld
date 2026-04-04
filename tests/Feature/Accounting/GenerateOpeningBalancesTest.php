<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\GenerateOpeningBalancesAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;

class GenerateOpeningBalancesTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['currency' => 'CHF']);

        // Create standard chart of accounts
        $this->createAccount('1020', 'Bank', AccountType::Asset);
        $this->createAccount('1100', 'Debtors', AccountType::Asset);
        $this->createAccount('2000', 'Creditors', AccountType::Liability);
        $this->createAccount('2800', 'Equity', AccountType::Equity);
        $this->createAccount('3000', 'Revenue', AccountType::Revenue);
        $this->createAccount('4000', 'Expenses', AccountType::Expense);
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

    public function test_generates_opening_balances_for_balance_sheet_accounts(): void
    {
        $orgId = $this->organization->id;
        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();
        $debtors = Account::where('organization_id', $orgId)->where('code', '1100')->first();
        $creditors = Account::where('organization_id', $orgId)->where('code', '2000')->first();

        // Post entries in 2025
        $this->postJournalEntry('2025-06-01', [
            $this->journalLine($bank, '10000.00', '0', 'Initial deposit'),
            $this->journalLine($debtors, '0', '10000.00', 'Contra'),
        ], 'JE-001');

        $this->postJournalEntry('2025-07-01', [
            $this->journalLine($debtors, '5000.00', '0', 'Invoice issued'),
            $this->journalLine($creditors, '0', '5000.00', 'Contra'),
        ], 'JE-002');

        $action = app(GenerateOpeningBalancesAction::class);
        $entry = $action->execute($orgId, 2025);

        $this->assertNotNull($entry);
        $this->assertTrue($entry->is_posted);
        $this->assertSame('OPENING-2026', $entry->reference);
        $this->assertSame('2026-01-01', $entry->date->toDateString());

        // Verify the entry is balanced
        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');
        $this->assertSame(0, bccomp((string) $totalDebit, (string) $totalCredit, 2));
    }

    public function test_excludes_revenue_and_expense_accounts(): void
    {
        $orgId = $this->organization->id;
        $revenue = Account::where('organization_id', $orgId)->where('code', '3000')->first();
        $expense = Account::where('organization_id', $orgId)->where('code', '4000')->first();
        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();

        // Post P&L entries
        $this->postJournalEntry('2025-03-01', [
            $this->journalLine($bank, '2000.00', '0', 'Received'),
            $this->journalLine($revenue, '0', '2000.00', 'Sales'),
        ], 'JE-PL1');

        $this->postJournalEntry('2025-04-01', [
            $this->journalLine($expense, '800.00', '0', 'Office supplies'),
            $this->journalLine($bank, '0', '800.00', 'Payment'),
        ], 'JE-PL2');

        $action = app(GenerateOpeningBalancesAction::class);
        $entry = $action->execute($orgId, 2025);

        // Only bank account should carry forward (1200 net balance)
        $lineCodes = $entry->lines->map(fn ($l) => $l->account->code)->toArray();
        $this->assertNotContains('3000', $lineCodes, 'Revenue should not carry forward');
        $this->assertNotContains('4000', $lineCodes, 'Expense should not carry forward');
    }

    public function test_returns_null_when_no_balances(): void
    {
        $action = app(GenerateOpeningBalancesAction::class);
        $entry = $action->execute($this->organization->id, 2025);

        $this->assertNull($entry);
    }

    public function test_skips_zero_balance_accounts(): void
    {
        $orgId = $this->organization->id;
        $bank = Account::where('organization_id', $orgId)->where('code', '1020')->first();
        $debtors = Account::where('organization_id', $orgId)->where('code', '1100')->first();

        // Create balanced entry (bank +1000, then -1000)
        $this->postJournalEntry('2025-01-01', [
            $this->journalLine($bank, '1000.00', '0', 'Deposit'),
            $this->journalLine($debtors, '0', '1000.00', 'Contra'),
        ], 'JE-Z1');

        $this->postJournalEntry('2025-06-01', [
            $this->journalLine($bank, '0', '1000.00', 'Withdrawal'),
            $this->journalLine($debtors, '1000.00', '0', 'Contra'),
        ], 'JE-Z2');

        $action = app(GenerateOpeningBalancesAction::class);
        $entry = $action->execute($orgId, 2025);

        $this->assertNull($entry, 'Should return null when all balances are zero');
    }
}
