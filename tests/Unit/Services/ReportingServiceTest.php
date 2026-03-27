<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        $this->organization = Organization::create([
            'name' => 'Reporting Test Org',
            'currency' => 'CHF',
        ]);
    }

    public function test_profit_and_loss_aggregates_non_zero_balances(): void
    {
        $revenue = $this->makeAccount('3000', 'Revenue', AccountType::Revenue);
        $zeroRevenue = $this->makeAccount('3200', 'Other Revenue', AccountType::Revenue);
        $expense = $this->makeAccount('6530', 'Software', AccountType::Expense);

        $ledgerService = Mockery::mock(LedgerService::class);
        $ledgerService->shouldReceive('accountBalance')->once()->with($revenue->id, '2026-01-01', '2026-03-31')->andReturn('1200.00');
        $ledgerService->shouldReceive('accountBalance')->once()->with($zeroRevenue->id, '2026-01-01', '2026-03-31')->andReturn('0.00');
        $ledgerService->shouldReceive('accountBalance')->once()->with($expense->id, '2026-01-01', '2026-03-31')->andReturn('350.50');

        $service = new ReportingService($ledgerService);

        $report = $service->profitAndLoss($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertSame('2026-01-01', $report['period']['from']);
        $this->assertSame('2026-03-31', $report['period']['to']);
        $this->assertCount(1, $report['revenue']);
        $this->assertCount(1, $report['expenses']);
        $this->assertSame('3000', $report['revenue'][0]['code']);
        $this->assertEquals('1200.00', $report['total_revenue']);
        $this->assertEquals('350.50', $report['total_expenses']);
        $this->assertSame('849.50', $report['net_profit']);
    }

    public function test_balance_sheet_groups_asset_liability_and_equity_sections(): void
    {
        $asset = $this->makeAccount('1020', 'Bank', AccountType::Asset);
        $liability = $this->makeAccount('2000', 'Payables', AccountType::Liability);
        $equity = $this->makeAccount('2800', 'Equity', AccountType::Equity);

        $ledgerService = Mockery::mock(LedgerService::class);
        $ledgerService->shouldReceive('accountBalance')->once()->with($asset->id, null, '2026-03-31')->andReturn('1500.00');
        $ledgerService->shouldReceive('accountBalance')->once()->with($liability->id, null, '2026-03-31')->andReturn('600.00');
        $ledgerService->shouldReceive('accountBalance')->once()->with($equity->id, null, '2026-03-31')->andReturn('900.00');

        $service = new ReportingService($ledgerService);

        $report = $service->balanceSheet($this->organization->id, '2026-03-31');

        $this->assertSame('2026-03-31', $report['as_of_date']);
        $this->assertEquals('1500.00', $report['assets']['total']);
        $this->assertEquals('600.00', $report['liabilities']['total']);
        $this->assertEquals('900.00', $report['equity']['total']);
        $this->assertSame('1020', $report['assets']['accounts'][0]['code']);
    }

    public function test_profit_and_loss_without_comparison_returns_null_comparison(): void
    {
        $revenue = $this->makeAccount('3000', 'Revenue', AccountType::Revenue);
        $expense = $this->makeAccount('6530', 'Software', AccountType::Expense);

        $ledgerService = Mockery::mock(LedgerService::class);
        $ledgerService->shouldReceive('accountBalance')->with($revenue->id, '2026-01-01', '2026-03-31')->andReturn('1000.00');
        $ledgerService->shouldReceive('accountBalance')->with($expense->id, '2026-01-01', '2026-03-31')->andReturn('400.00');

        $service = new ReportingService($ledgerService);
        $report = $service->profitAndLoss($this->organization->id, '2026-01-01', '2026-03-31');

        $this->assertNull($report['comparison']);
        $this->assertNull($report['variance']);
    }

    public function test_profit_and_loss_with_comparison_returns_variance(): void
    {
        $revenue = $this->makeAccount('3000', 'Revenue', AccountType::Revenue);
        $expense = $this->makeAccount('6530', 'Software', AccountType::Expense);

        $ledgerService = Mockery::mock(LedgerService::class);

        // Current period
        $ledgerService->shouldReceive('accountBalance')->with($revenue->id, '2026-01-01', '2026-12-31')->andReturn('12000.00');
        $ledgerService->shouldReceive('accountBalance')->with($expense->id, '2026-01-01', '2026-12-31')->andReturn('8000.00');

        // Comparison period (previous year)
        $ledgerService->shouldReceive('accountBalance')->with($revenue->id, '2025-01-01', '2025-12-31')->andReturn('10000.00');
        $ledgerService->shouldReceive('accountBalance')->with($expense->id, '2025-01-01', '2025-12-31')->andReturn('7000.00');

        $service = new ReportingService($ledgerService);
        $report = $service->profitAndLoss(
            $this->organization->id,
            '2026-01-01',
            '2026-12-31',
            '2025-01-01',
            '2025-12-31',
        );

        // Comparison data present
        $this->assertNotNull($report['comparison']);
        $this->assertSame('2025-01-01', $report['comparison']['period']['from']);
        $this->assertEquals('10000.00', $report['comparison']['total_revenue']);
        $this->assertEquals('7000.00', $report['comparison']['total_expenses']);
        $this->assertSame('3000.00', $report['comparison']['net_profit']);

        // Variance data
        $this->assertNotNull($report['variance']);
        $this->assertSame('2000.00', $report['variance']['total_revenue']['amount']);
        $this->assertSame('20.00', $report['variance']['total_revenue']['percentage']);
        $this->assertSame('1000.00', $report['variance']['total_expenses']['amount']);
        $this->assertSame('1000.00', $report['variance']['net_profit']['amount']);
        // Net profit: 4000 - 3000 = 1000, 1000/3000 * 100 = 33.33%
        $this->assertSame('33.33', $report['variance']['net_profit']['percentage']);
    }

    private function makeAccount(string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code' => $code,
            'name' => $name,
            'type' => $type->value,
            'is_active' => true,
        ]);
    }
}
