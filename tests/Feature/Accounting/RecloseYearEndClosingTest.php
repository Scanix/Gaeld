<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\ReopenFiscalYearAction;
use App\Domains\Accounting\Actions\YearEndClosingAction;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;

class RecloseYearEndClosingTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        $this->createAccount('1020', 'Bank', AccountType::Asset);
        $this->createAccount('3000', 'Revenue', AccountType::Revenue);
        $this->createAccount('6500', 'Office expenses', AccountType::Expense);
        $this->createAccount('9000', 'Opening balance', AccountType::Equity);
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

    public function test_reclosing_after_reopen_versions_the_closing_reference(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => '2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => FiscalYearStatus::Operative,
        ]);

        $bank = Account::where('organization_id', $this->organization->id)
            ->where('code', '1020')
            ->firstOrFail();
        $revenue = Account::where('organization_id', $this->organization->id)
            ->where('code', '3000')
            ->firstOrFail();
        $expense = Account::where('organization_id', $this->organization->id)
            ->where('code', '6500')
            ->firstOrFail();

        $this->postJournalEntry('2024-06-01', [
            $this->journalLine($bank, '100.00', '0.00'),
            $this->journalLine($revenue, '0.00', '100.00'),
        ], 'REVENUE-2024');

        $validated = [
            'year' => 2024,
            'fiscal_year_id' => $fiscalYear->id,
            'closing_date' => '2024-12-31',
            'reference' => 'BOUCL-2024',
            'result_account_code' => '9000',
        ];

        app(YearEndClosingAction::class)->execute($this->organization, $validated, $this->user);

        app(ReopenFiscalYearAction::class)->execute(
            $this->organization,
            ['year' => 2024, 'fiscal_year_id' => $fiscalYear->id],
            $this->user,
        );

        $this->postJournalEntry('2024-12-15', [
            $this->journalLine($expense, '50.00', '0.00'),
            $this->journalLine($bank, '0.00', '50.00'),
        ], 'REOPEN-ADJUSTMENT');

        app(YearEndClosingAction::class)->execute($this->organization, $validated, $this->user);

        $references = JournalEntry::query()
            ->where('organization_id', $this->organization->id)
            ->where('type', 'year_end_closing')
            ->orderBy('created_at')
            ->pluck('reference')
            ->all();

        sort($references);
        $this->assertSame(['BOUCL-2024', 'BOUCL-2024-v2'], $references);
        $this->assertTrue($fiscalYear->fresh()->isClosed());
    }
}
