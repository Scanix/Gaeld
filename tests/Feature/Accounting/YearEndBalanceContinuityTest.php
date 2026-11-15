<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\GenerateOpeningBalancesAction;
use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;

class YearEndBalanceContinuityTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase;

    private Organization $organization;

    /** @var array<string, Account> */
    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['currency' => 'CHF']);
        $this->accounts = [];

        foreach ([
            'bank' => ['1020', AccountType::Asset],
            'debtors' => ['1100', AccountType::Asset],
            'loan' => ['2000', AccountType::Liability],
            'equity' => ['2800', AccountType::Equity],
            'result' => ['2900', AccountType::Equity],
            'revenue' => ['3000', AccountType::Revenue],
            'expense' => ['4000', AccountType::Expense],
            'opening' => [AccountCode::OPENING_BALANCE, AccountType::Equity],
        ] as $name => [$code, $type]) {
            $this->accounts[$name] = Account::factory()->for($this->organization)->create([
                'code' => $code,
                'type' => $type,
            ]);
        }
    }

    public function test_year_end_closing_keeps_the_bank_balance_at_chf_68(): void
    {
        $this->recordIncomeAndExpense(2025, '2568.00', '2500.00');
        $query = app(LedgerQueryService::class);
        $this->assertSame('68.00', $query->accountBalance($this->accounts['bank']->id));

        $this->closeYear(2025);

        $opening = JournalEntry::where('reference', 'OPENING-2026')->firstOrFail();
        $this->assertSame('68.00', (string) $opening->lines->firstWhere('account_id', $this->accounts['bank']->id)->debit);
        $this->assertSame('68.00', $query->accountBalance($this->accounts['bank']->id));
        $this->assertSame('68.00', $query->accountBalance($this->accounts['bank']->id, null, '2025-12-31'));
        $this->assertSame('68.00', $query->accountBalance($this->accounts['result']->id));

        $trialBalance = collect($query->trialBalance($this->organization->id))->keyBy('account_code');
        $this->assertSame('68.00', $trialBalance['1020']['debit']);
        $this->assertSame('68.00', $trialBalance['2900']['credit']);
    }

    public function test_consecutive_closings_carry_forward_balances_only_once(): void
    {
        $this->recordIncomeAndExpense(2025, '2568.00', '2500.00');
        $this->closeYear(2025);
        $this->recordIncomeAndExpense(2026, '100.00', '30.00');
        $this->postJournalEntry('2026-06-01', [
            $this->journalLine($this->accounts['bank'], '50.00', '0'),
            $this->journalLine($this->accounts['loan'], '0', '50.00'),
        ], 'LOAN-2026');

        $this->closeYear(2026);

        $opening = JournalEntry::where('reference', 'OPENING-2027')->firstOrFail();
        $this->assertSame('188.00', (string) $opening->lines->firstWhere('account_id', $this->accounts['bank']->id)->debit);
        $this->assertSame('138.00', (string) $opening->lines->firstWhere('account_id', $this->accounts['result']->id)->credit);
        $this->assertSame('50.00', (string) $opening->lines->firstWhere('account_id', $this->accounts['loan']->id)->credit);

        $query = app(LedgerQueryService::class);
        $this->assertSame('188.00', $query->accountBalance($this->accounts['bank']->id));
        $this->assertSame('138.00', $query->accountBalance($this->accounts['result']->id));
        $this->assertSame('50.00', $query->accountBalance($this->accounts['loan']->id));
        $this->assertSame('188.00', $query->accountBalance($this->accounts['bank']->id, null, '2026-12-31'));
        $this->assertSame('68.00', $query->accountBalance($this->accounts['bank']->id, null, '2025-12-31'));
    }

    public function test_failed_closing_does_not_cache_rolled_back_balances(): void
    {
        $this->recordIncomeAndExpense(2025, '2568.00', '2500.00');
        $this->postBankEquity('2026-01-01', 'OPENING-2026', '1.00');

        try {
            $this->closeYear(2025);
            $this->fail('Expected the existing opening reference to prevent closing.');
        } catch (DuplicateReferenceException) {
            $this->assertDatabaseMissing('journal_entries', ['reference' => 'YE-2025']);
        }

        $query = app(LedgerQueryService::class);
        $this->assertSame('0.00', $query->accountBalance($this->accounts['result']->id, null, '2025-12-31'));
        $this->assertSame('68.00', $query->accountBalance($this->accounts['bank']->id, null, '2025-12-31'));
    }

    public function test_balance_date_ranges_preserve_period_activity_and_historical_profit_and_loss(): void
    {
        $this->recordIncomeAndExpense(2025, '2568.00', '2500.00');
        app(GenerateOpeningBalancesAction::class)->execute($this->organization->id, 2025);
        $this->recordIncomeAndExpense(2026, '20.00', '10.00');

        $query = app(LedgerQueryService::class);
        $this->assertSame('78.00', $query->accountBalance($this->accounts['bank']->id, null, '2026-12-31'));
        $this->assertSame('-10.00', $query->accountBalance($this->accounts['bank']->id, '2026-04-01', '2026-12-31'));
        $this->assertSame('2588.00', $query->accountBalance($this->accounts['revenue']->id, '2025-01-01', '2026-12-31'));
        $this->assertSame('2510.00', $query->accountBalance($this->accounts['expense']->id, '2025-01-01', '2026-12-31'));
        $this->assertSame('2568.00', $query->accountBalance($this->accounts['revenue']->id, '2025-01-01', '2025-12-31'));
    }

    public function test_accounts_omitted_from_a_later_opening_keep_their_zero_balance(): void
    {
        $this->postBankEquity('2025-06-01', 'CAPITAL', '100.00');
        app(GenerateOpeningBalancesAction::class)->execute($this->organization->id, 2025);
        $this->postJournalEntry('2026-06-01', [
            $this->journalLine($this->accounts['debtors'], '100.00', '0'),
            $this->journalLine($this->accounts['bank'], '0', '100.00'),
        ], 'TRANSFER');

        $opening = app(GenerateOpeningBalancesAction::class)->execute($this->organization->id, 2026);

        $this->assertNotNull($opening);
        $this->assertNull($opening->lines->firstWhere('account_id', $this->accounts['bank']->id));
        $query = app(LedgerQueryService::class);
        $this->assertSame('0.00', $query->accountBalance($this->accounts['bank']->id));
        $this->assertSame('100.00', $query->accountBalance($this->accounts['debtors']->id));
        $this->assertNotContains('1020', array_column($query->trialBalance($this->organization->id), 'account_code'));
    }

    public function test_only_posted_full_openings_in_the_organization_and_cutoff_reset_balances(): void
    {
        $this->postBankEquity('2025-06-01', 'CAPITAL', '100.00');
        app(GenerateOpeningBalancesAction::class)->execute($this->organization->id, 2025);
        $this->postBankEquity('2026-02-01', 'CAPITAL-INCREASE', '20.00');
        $this->postBankEquity('2026-04-01', 'OPENING-RESTATEMENT-2026', '5.00');
        $this->postBankEquity('2026-06-01', 'OPENING-2026-adjustment', '7.00');
        $this->createBankEquityDraft('2026-07-01', 'OPENING-2026-v2', '999.00');
        $this->postBankEquity('2027-01-01', 'OPENING-2027', '132.00');

        $foreignOrganization = Organization::factory()->create();
        $foreignBank = Account::factory()->for($foreignOrganization)->asset()->create();
        $foreignEquity = Account::factory()->for($foreignOrganization)->equity()->create();
        app(LedgerService::class)->postEntry($foreignOrganization->id, new JournalEntryData(
            date: '2026-08-01',
            reference: 'OPENING-2026',
            description: 'Foreign opening',
            lines: [
                $this->journalLine($foreignBank, '500.00', '0'),
                $this->journalLine($foreignEquity, '0', '500.00'),
            ],
        ));

        $query = app(LedgerQueryService::class);
        $this->assertSame('132.00', $query->accountBalance($this->accounts['bank']->id, null, '2026-12-31'));
        $this->assertSame('132.00', $query->accountBalance($this->accounts['equity']->id, null, '2026-12-31'));
        $trialBalance = collect($query->trialBalance($this->organization->id, '2026-12-31'))->keyBy('account_code');
        $this->assertSame('132.00', $trialBalance['1020']['debit']);
        $this->assertSame('132.00', $trialBalance['2800']['credit']);
    }

    public function test_trial_balance_excludes_drafts_and_transactions_after_the_cutoff_without_openings(): void
    {
        $this->postBankEquity('2025-01-01', 'CAPITAL', '100.00');
        $this->createBankEquityDraft('2025-02-01', 'DRAFT', '900.00');
        $this->postBankEquity('2026-01-01', 'FUTURE', '50.00');

        $query = app(LedgerQueryService::class);
        $trialBalance = collect($query->trialBalance($this->organization->id, '2025-12-31'))->keyBy('account_code');
        $this->assertSame('100.00', $trialBalance['1020']['debit']);
        $this->assertSame('100.00', $trialBalance['2800']['credit']);
        $this->assertSame('100.00', $query->accountBalance($this->accounts['bank']->id, null, '2025-12-31'));
    }

    public function test_versioned_opening_on_a_non_calendar_date_resets_the_balance(): void
    {
        $this->postBankEquity('2025-06-01', 'CAPITAL', '100.00');
        $this->postBankEquity('2026-07-01', 'OPENING-2026-v2', '100.00');
        $this->postBankEquity('2026-08-01', 'CAPITAL-INCREASE', '20.00');

        $query = app(LedgerQueryService::class);
        $this->assertSame('100.00', $query->accountBalance($this->accounts['bank']->id, null, '2026-06-30'));
        $this->assertSame('120.00', $query->accountBalance($this->accounts['bank']->id, null, '2026-12-31'));
        $this->assertSame('20.00', $query->accountBalance($this->accounts['bank']->id, '2026-08-01', '2026-12-31'));
    }

    private function postBankEquity(string $date, string $reference, string $amount): void
    {
        $this->postJournalEntry($date, [
            $this->journalLine($this->accounts['bank'], $amount, '0'),
            $this->journalLine($this->accounts['equity'], '0', $amount),
        ], $reference);
    }

    private function createBankEquityDraft(string $date, string $reference, string $amount): void
    {
        app(LedgerService::class)->createDraft($this->organization->id, new JournalEntryData(
            date: $date,
            reference: $reference,
            description: 'Draft bank entry',
            lines: [
                $this->journalLine($this->accounts['bank'], $amount, '0'),
                $this->journalLine($this->accounts['equity'], '0', $amount),
            ],
        ));
    }

    private function recordIncomeAndExpense(int $year, string $income, string $expense): void
    {
        $this->postJournalEntry("{$year}-03-01", [
            $this->journalLine($this->accounts['bank'], $income, '0'),
            $this->journalLine($this->accounts['revenue'], '0', $income),
        ], "INCOME-{$year}");

        $this->postJournalEntry("{$year}-09-01", [
            $this->journalLine($this->accounts['expense'], $expense, '0'),
            $this->journalLine($this->accounts['bank'], '0', $expense),
        ], "EXPENSE-{$year}");
    }

    private function closeYear(int $year): void
    {
        app(YearEndClosingAction::class)->execute($this->organization, [
            'year' => $year,
            'closing_date' => "{$year}-12-31",
            'reference' => "YE-{$year}",
            'result_account_code' => '2900',
        ], User::factory()->create());
    }
}
