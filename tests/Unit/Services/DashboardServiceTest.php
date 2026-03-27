<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Reporting\Services\DashboardService;
use App\Support\DTOs\SummaryResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_metrics_aggregates_summaries_recent_entries_and_monthly_breakdown(): void
    {
        Carbon::setTestNow('2026-03-20 12:00:00');

        $ledgerService = Mockery::mock(LedgerService::class);
        $invoiceService = Mockery::mock(InvoiceService::class);
        $expenseService = Mockery::mock(ExpenseService::class);

        $bankAccount = new Account([
            'code' => '1020',
            'name' => 'Bank',
        ]);
        $bankAccount->id = 10;

        $invoiceService->shouldReceive('yearlyRevenue')->once()->with('org-1', 2026)->andReturn('1500.00');
        $expenseService->shouldReceive('yearlyTotal')->once()->with('org-1', 2026)->andReturn('450.00');
        $invoiceService->shouldReceive('unpaidSummary')->once()->with('org-1')->andReturn(new SummaryResult(2, '700.00'));
        $expenseService->shouldReceive('pendingSummary')->once()->with('org-1')->andReturn(new SummaryResult(1, '120.00'));
        $ledgerService->shouldReceive('resolveAccount')->once()->with('org-1', '1020')->andReturn($bankAccount);
        $ledgerService->shouldReceive('accountBalance')->once()->with(10)->andReturn('800.00');
        $ledgerService->shouldReceive('recentEntries')->once()->with('org-1')->andReturn(collect([
            $this->makeEntry('je-1', '2026-03-02', 'Invoice payment', 'PAY-1', '3000', '500.00'),
            $this->makeEntry('je-2', '2026-03-04', 'Software expense', 'EXP-1', '6530', '120.00'),
        ]));

        $invoiceService->shouldReceive('paidInYear')->once()->with('org-1', 2026)->andReturn(collect([
            (object) ['number' => 'INV-1', 'total' => '100.00', 'issue_date' => '2026-01-10'],
            (object) ['number' => 'INV-2', 'total' => '200.00', 'issue_date' => '2026-03-08'],
        ]));
        $expenseService->shouldReceive('inYear')->once()->with('org-1', 2026)->andReturn(collect([
            (object) ['description' => 'Hosting', 'amount' => '50.00', 'date' => '2026-01-12'],
            (object) ['description' => 'Tools', 'amount' => '70.00', 'date' => '2026-03-09'],
        ]));
        $invoiceService->shouldReceive('sentOrOverdueDueInYear')->once()->with('org-1', 2026)->andReturn(collect([
            (object) ['number' => 'INV-3', 'total' => '300.00', 'due_date' => '2026-03-25'],
        ]));

        $service = new DashboardService($ledgerService, $invoiceService, $expenseService);

        $metrics = $service->metrics('org-1');

        $this->assertSame('1500.00', $metrics['revenue']);
        $this->assertSame('450.00', $metrics['expenses']);
        $this->assertSame('800.00', $metrics['cashBalance']);
        $this->assertSame('1050.00', $metrics['balance']);
        $this->assertSame(2, $metrics['unpaidInvoices']['count']);
        $this->assertSame('700.00', $metrics['unpaidInvoices']['total']);
        $this->assertSame('income', $metrics['recentTransactions']->first()['type']);
        $this->assertSame('expense', $metrics['recentTransactions']->last()['type']);
        $this->assertCount(12, $metrics['monthlyBreakdown']['labels']);
        $this->assertSame('100', (string) $metrics['monthlyBreakdown']['revenue'][0]);
        $this->assertSame('300', (string) $metrics['monthlyBreakdown']['forecast'][2]);
    }

    public function test_metrics_returns_zero_cash_balance_when_bank_account_is_missing(): void
    {
        Carbon::setTestNow('2026-03-20 12:00:00');

        $ledgerService = Mockery::mock(LedgerService::class);
        $invoiceService = Mockery::mock(InvoiceService::class);
        $expenseService = Mockery::mock(ExpenseService::class);

        $invoiceService->shouldReceive('yearlyRevenue')->once()->andReturn('0.00');
        $expenseService->shouldReceive('yearlyTotal')->once()->andReturn('0.00');
        $invoiceService->shouldReceive('unpaidSummary')->once()->andReturn(new SummaryResult(0, '0.00'));
        $expenseService->shouldReceive('pendingSummary')->once()->andReturn(new SummaryResult(0, '0.00'));
        $ledgerService->shouldReceive('resolveAccount')->once()->andThrow(new ModelNotFoundException);
        $ledgerService->shouldReceive('recentEntries')->once()->andReturn(collect());
        $invoiceService->shouldReceive('paidInYear')->once()->andReturn(collect());
        $expenseService->shouldReceive('inYear')->once()->andReturn(collect());
        $invoiceService->shouldReceive('sentOrOverdueDueInYear')->once()->andReturn(collect());

        $service = new DashboardService($ledgerService, $invoiceService, $expenseService);

        $metrics = $service->metrics('org-1');

        $this->assertSame('0.00', $metrics['cashBalance']);
        $this->assertTrue($metrics['recentTransactions']->isEmpty());
    }

    private function makeEntry(string $id, string $date, string $description, string $reference, string $accountCode, string $amount): JournalEntry
    {
        $account = new Account(['code' => $accountCode]);

        $line = new TransactionLine([
            'debit' => $amount,
            'credit' => '0.00',
        ]);
        $line->setRelation('account', $account);

        $entry = new JournalEntry([
            'id' => $id,
            'date' => $date,
            'description' => $description,
            'reference' => $reference,
        ]);
        $entry->exists = true;
        $entry->setRelation('lines', new Collection([$line]));

        return $entry;
    }
}
