<?php

namespace Tests\Feature\Reporting;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class ReportExportTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Export Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

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
        $ledger = app(LedgerService::class);
        $ledger->postEntry($this->org->id, new JournalEntryData(
            date: '2026-01-15',
            reference: 'TEST-001',
            description: 'Test Revenue Entry',
            lines: [
                new JournalLineData(accountId: (string) $bank->id, debit: '1000.00', credit: '0', description: 'Bank deposit'),
                new JournalLineData(accountId: (string) $revenue->id, debit: '0', credit: '1000.00', description: 'Service revenue'),
            ],
        ));
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
            ->get(route('reports.balanceSheet.export', [
                'format' => 'csv',
                'as_of_date' => '2026-12-31',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_trial_balance_csv(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('accounting.trialBalance.export', [
                'format' => 'csv',
                'as_of_date' => '2026-12-31',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_journal_entries_csv(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('accounting.journalEntries.export', [
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
