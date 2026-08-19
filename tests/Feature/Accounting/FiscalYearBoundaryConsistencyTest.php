<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Enums\VatEntryType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Models\VatEntry;
use App\Domains\Accounting\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class FiscalYearBoundaryConsistencyTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_export_index_exposes_explicit_fiscal_year_dates(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Closed,
        ]);

        $this->actAsOrg()
            ->get(route('accounting.export'))
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Export')
                ->where('fiscalYears.0.id', $fiscalYear->id)
                ->where('fiscalYears.0.start_date', '2024-01-01')
                ->where('fiscalYears.0.end_date', '2025-06-30')
            );
    }

    public function test_profit_and_loss_uses_explicit_fiscal_year_range(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);

        $this->actAsOrg()
            ->get(route('reports.pnl', ['fiscal_year_id' => $fiscalYear->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Reports/ProfitAndLoss')
                ->where('report.period.from', '2024-01-01')
                ->where('report.period.to', '2025-06-30')
            );
    }

    public function test_year_end_closing_checks_vat_periods_in_the_full_long_year(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);
        $account = Account::factory()->for($this->organization)->create([
            'type' => 'asset',
        ]);
        $contraAccount = Account::factory()->for($this->organization)->create([
            'type' => 'revenue',
        ]);
        $entry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2025-05-15',
            'reference' => 'VAT-Q2-2025',
            'description' => 'VAT activity in long fiscal year',
            'is_posted' => true,
        ]);
        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => '100.00',
            'credit' => '0.00',
        ]);
        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $contraAccount->id,
            'debit' => '0.00',
            'credit' => '100.00',
        ]);
        VatEntry::create([
            'journal_entry_id' => $entry->id,
            'vat_rate_id' => VatRate::factory()->for($this->organization)->create()->id,
            'base_amount' => '100.00',
            'vat_amount' => '8.10',
            'type' => VatEntryType::Output,
        ]);

        $this->actAsOrg()
            ->get(route('accounting.closing', ['fiscal_year_id' => $fiscalYear->id]))
            ->assertInertia(fn ($page) => $page
                ->where('fromDate', '2024-01-01')
                ->where('toDate', '2025-06-30')
                ->where('unsettledVatPeriods.0', 'Q2 2025')
            );
    }
}
