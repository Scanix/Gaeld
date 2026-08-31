<?php

namespace Tests\Feature\Reporting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Reporting\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;
use Tests\Traits\WithAuthenticatedOrganization;

class CashFlowReportTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase, WithAuthenticatedOrganization;

    private Account $bankAccount;

    private Account $arAccount;

    private Account $revenueAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-20 12:00:00');

        $this->setUpOrganization();

        $this->bankAccount = Account::create(['organization_id' => $this->organization->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        $this->arAccount = Account::create(['organization_id' => $this->organization->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value]);
        $this->revenueAccount = Account::create(['organization_id' => $this->organization->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        $this->expenseAccount = Account::create(['organization_id' => $this->organization->id, 'code' => '6530', 'name' => 'Expense', 'type' => AccountType::Expense->value]);

        // AP account
        Account::create(['organization_id' => $this->organization->id, 'code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->organization->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────

    public function test_cash_flow_returns_expected_structure(): void
    {
        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $report = $service->cashFlow($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertArrayHasKey('net_income', $report);
        $this->assertArrayHasKey('operating', $report);
        $this->assertArrayHasKey('investing', $report);
        $this->assertArrayHasKey('financing', $report);
        $this->assertArrayHasKey('net_change', $report);
        $this->assertArrayHasKey('beginning_cash', $report);
        $this->assertArrayHasKey('ending_cash', $report);
    }

    public function test_net_income_equals_pnl_net_profit(): void
    {
        // Post one revenue entry: Debit AR, Credit Revenue
        $this->postJournalEntry('2026-01-15', [
            $this->journalLine($this->arAccount, '1000.00', '0.00'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00'),
        ]);

        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $report = $service->cashFlow($this->organization->id, '2026-01-01', '2026-03-31');

        // Revenue account is credit-normal so balance = credits - debits = 1000
        // Expense = 0, so net_profit = 1000
        $this->assertEquals('1000.00', $report['net_income']);
    }

    public function test_ending_cash_equals_beginning_plus_net_change(): void
    {
        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $report = $service->cashFlow($this->organization->id, '2026-01-01', '2026-03-31');

        $expectedEnding = bcadd($report['beginning_cash'], $report['net_change'], 2);
        $this->assertEquals($expectedEnding, $report['ending_cash']);
    }

    public function test_operating_total_plus_investing_plus_financing_equals_net_change(): void
    {
        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $report = $service->cashFlow($this->organization->id, '2026-01-01', '2026-03-31');

        $computed = bcadd(bcadd($report['operating']['total'], $report['investing']['total'], 2), $report['financing']['total'], 2);
        $this->assertEquals($report['net_change'], $computed);
    }

    public function test_populated_cash_flow_exposes_operating_total_for_cash_receipt(): void
    {
        $this->postJournalEntry('2026-02-15', [
            $this->journalLine($this->bankAccount, '1000.00', '0.00'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00'),
        ]);

        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $report = $service->cashFlow($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertSame('1000.00', $report['operating']['total']);
        $this->assertSame('1000.00', $report['net_change']);
        $this->assertSame('1000.00', $report['ending_cash']);
    }

    public function test_expense_payment_is_reconciled_in_operating_cash_flow(): void
    {
        $vatAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1170',
            'name' => 'Input VAT',
            'type' => AccountType::Asset->value,
        ]);

        $this->postJournalEntry('2026-02-15', [
            $this->journalLine($this->expenseAccount, '120.00', '0.00'),
            $this->journalLine($vatAccount, '9.72', '0.00'),
            $this->journalLine($this->bankAccount, '0.00', '129.72'),
        ]);

        /** @var ReportingService $service */
        $service = app(ReportingService::class);
        $report = $service->cashFlow($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertSame('-129.72', $report['operating']['total']);
        $this->assertSame('-129.72', $report['net_change']);
        $this->assertSame('-129.72', $report['ending_cash']);
    }

    public function test_cash_flow_route_returns_inertia_response(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.cash-flow', ['from' => '2026-01-01', 'to' => '2026-03-31']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Reports/CashFlow'));
    }

    public function test_cash_flow_route_preserves_the_calculated_report_contract(): void
    {
        $this->postJournalEntry('2026-02-15', [
            $this->journalLine($this->bankAccount, '1000.00', '0.00'),
            $this->journalLine($this->revenueAccount, '0.00', '1000.00'),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.cash-flow', ['from' => '2026-01-01', 'to' => '2026-03-31']));

        $response->assertInertia(fn ($page) => $page
            ->where('report.operating.total', '1000.00')
            ->where('report.beginning_cash', '0.00')
            ->where('report.ending_cash', '1000.00')
            ->missing('report.beginning_balance')
            ->missing('report.ending_balance'));
    }

    public function test_cash_flow_export_pdf_returns_correct_content_type(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.cash-flow.export', ['format' => 'pdf', 'from' => '2026-01-01', 'to' => '2026-03-31']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_cash_flow_export_csv_returns_correct_content_type(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.cash-flow.export', ['format' => 'csv', 'from' => '2026-01-01', 'to' => '2026-03-31']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
