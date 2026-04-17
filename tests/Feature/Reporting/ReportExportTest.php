<?php

namespace Tests\Feature\Reporting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;
use Tests\Traits\WithAuthenticatedOrganization;

class ReportExportTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();

        // Create accounts
        $revenue = Account::create([
            'organization_id' => $this->org->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $bank = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);

        Account::create([
            'organization_id' => $this->org->id,
            'code' => '2200',
            'name' => 'VAT Output',
            'type' => AccountType::Liability->value,
        ]);

        // Post a journal entry so reports have data
        $this->postJournalEntry('2026-01-15', [
            $this->journalLine($bank, '1000.00', '0', 'Bank deposit'),
            $this->journalLine($revenue, '0', '1000.00', 'Service revenue'),
        ], 'TEST-001', 'Test Revenue Entry');
    }

    public function test_export_profit_and_loss_csv(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.pnl.export', [
                'format' => 'csv',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_balance_sheet_csv(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.balance-sheet.export', [
                'format' => 'csv',
                'as_of_date' => '2026-12-31',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_trial_balance_csv(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('accounting.trial-balance.export', [
                'format' => 'csv',
                'as_of_date' => '2026-12-31',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_journal_entries_csv(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('accounting.journal-entries.export', [
                'format' => 'csv',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_invalid_format_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.pnl.export', ['format' => 'xlsx']));

        $response->assertNotFound();
    }

    public function test_export_unauthenticated_redirects(): void
    {
        $response = $this->get(route('reports.pnl.export', ['format' => 'csv']));

        $response->assertRedirect(route('login'));
    }
}
