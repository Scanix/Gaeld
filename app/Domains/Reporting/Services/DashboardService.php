<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Invoicing\Services\InvoiceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates KPI data for the organization dashboard: revenue/expense
 * summaries, cash flow, outstanding invoices, and recent activity.
 */
class DashboardService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly InvoiceService $invoiceService,
        private readonly ExpenseService $expenseService,
        private readonly VatReportService $vatReportService,
        private readonly AgingReportService $agingReportService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{revenue: string, expenses: string, cashBalance: string, unpaidInvoices: array{count: int, total: string}, pendingExpenses: array{count: int, total: string}, balance: string, recentTransactions: Collection, monthlyBreakdown: array}
     */
    public function metrics(string $organizationId): array
    {
        return Cache::tags(["org:{$organizationId}:dashboard"])->remember(
            "dashboard_metrics:{$organizationId}",
            300, // 5 minutes
            fn () => $this->computeMetrics($organizationId)
        );
    }

    public function flushCache(string $organizationId): void
    {
        Cache::tags(["org:{$organizationId}:dashboard"])->flush();
    }

    // ──────────────────────────────────────────────────────────────
    //  Core Computation
    // ──────────────────────────────────────────────────────────────

    private function computeMetrics(string $organizationId): array
    {
        $year = now()->year;

        $totalRevenue = $this->invoiceService->yearlyRevenue($organizationId, $year);
        $totalExpenses = $this->expenseService->yearlyTotal($organizationId, $year);
        $cashBalance = $this->cashBalance($organizationId);

        $unpaidInvoices = $this->invoiceService->unpaidSummary($organizationId);

        $pendingExpenses = $this->expenseService->pendingSummary($organizationId);

        // Year-over-year comparison
        $previousRevenue = $this->invoiceService->yearlyRevenue($organizationId, $year - 1);
        $previousExpenses = $this->expenseService->yearlyTotal($organizationId, $year - 1);

        return [
            'revenue' => $totalRevenue,
            'expenses' => $totalExpenses,
            'cashBalance' => $cashBalance,
            'unpaidInvoices' => [
                'count' => $unpaidInvoices->count,
                'total' => $unpaidInvoices->total,
            ],
            'pendingExpenses' => [
                'count' => $pendingExpenses->count,
                'total' => $pendingExpenses->total,
            ],
            'balance' => bcsub($totalRevenue, $totalExpenses, 2),
            'previousRevenue' => $previousRevenue,
            'previousExpenses' => $previousExpenses,
            'previousBalance' => bcsub($previousRevenue, $previousExpenses, 2),
            'recentTransactions' => $this->recentTransactions($organizationId),
            'monthlyBreakdown' => $this->monthlyBreakdown($organizationId, $year),
            'budgetSummary' => $this->budgetSummary($organizationId, $year),
            'vatSummary' => $this->currentQuarterVat($organizationId),
            'receivablesAging' => $this->agingSummary($organizationId),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Component Metrics
    // ──────────────────────────────────────────────────────────────

    private function cashBalance(string $organizationId): string
    {
        try {
            $bankAccount = $this->ledgerService->resolveAccount($organizationId, AccountCode::BANK_CASH);
        } catch (ModelNotFoundException) {
            return '0.00';
        }

        return $this->ledgerService->accountBalance($bankAccount->id);
    }

    private function recentTransactions(string $organizationId): Collection
    {
        return $this->ledgerService->recentEntries($organizationId)
            ->map(function (JournalEntry $entry) {
                return [
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'description' => $entry->description,
                    'reference' => $entry->reference,
                    'amount' => (string) $entry->lines->sum('debit'),
                    'type' => $this->classifyTransactionType($entry),
                ];
            });
    }

    private function classifyTransactionType(JournalEntry $entry): string
    {
        $hasRevenue = $entry->lines->contains(fn ($line) => AccountCode::isRevenue($line->account?->code ?? ''));

        if ($hasRevenue) {
            return 'income';
        }

        $hasExpense = $entry->lines->contains(fn ($line) => AccountCode::isExpense($line->account?->code ?? ''));

        return $hasExpense ? 'expense' : 'transfer';
    }

    private function monthlyBreakdown(string $organizationId, int $year): array
    {
        // Fetch all data for the year in 3 queries instead of 6*12
        $paidInvoices = $this->invoiceService->paidInYear($organizationId, $year)
            ->groupBy(fn ($i) => Carbon::parse($i->issue_date)->month);

        $expenses = $this->expenseService->inYear($organizationId, $year)
            ->groupBy(fn ($e) => Carbon::parse($e->date)->month);

        $forecastInvoices = $this->invoiceService->sentOrOverdueDueInYear($organizationId, $year)
            ->groupBy(fn ($i) => Carbon::parse($i->due_date)->month);

        $monthlyData = collect(range(1, 12))->map(function ($month) use ($year, $paidInvoices, $expenses, $forecastInvoices) {
            $monthPaid = $paidInvoices->get($month, collect());
            $monthExpenses = $expenses->get($month, collect());
            $monthForecast = $forecastInvoices->get($month, collect());

            return [
                'month' => Carbon::create($year, $month, 1)->format('M'),
                'revenue' => (string) $monthPaid->sum('total'),
                'expenses' => (string) $monthExpenses->sum('amount'),
                'forecast' => (string) $monthForecast->sum('total'),
                'revenueItems' => $monthPaid->map(fn ($i) => $i->number.': '.number_format((float) $i->total, 2, '.', "'"))->values(),
                'expenseItems' => $monthExpenses->map(fn ($e) => $e->description.': '.number_format((float) $e->amount, 2, '.', "'"))->values(),
                'forecastItems' => $monthForecast->map(fn ($i) => $i->number.': '.number_format((float) $i->total, 2, '.', "'"))->values(),
            ];
        });

        return [
            'labels' => $monthlyData->pluck('month')->values(),
            'revenue' => $monthlyData->pluck('revenue')->values(),
            'expenses' => $monthlyData->pluck('expenses')->values(),
            'forecast' => $monthlyData->pluck('forecast')->values(),
            'revenueItems' => $monthlyData->pluck('revenueItems')->values(),
            'expenseItems' => $monthlyData->pluck('expenseItems')->values(),
            'forecastItems' => $monthlyData->pluck('forecastItems')->values(),
        ];
    }

    /**
     * Budget vs actual summary for the current fiscal year.
     *
     * Returns null when no budgets are configured.
     *
     * @return array{budgetedRevenue: string, budgetedExpenses: string, actualRevenue: string, actualExpenses: string, revenueVariance: string, expenseVariance: string, monthsElapsed: int}|null
     */
    private function budgetSummary(string $organizationId, int $year): ?array
    {
        $budgets = Budget::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->forYear($year)
            ->with('account')
            ->get();

        if ($budgets->isEmpty()) {
            return null;
        }

        $monthsElapsed = now()->month;

        $budgetedRevenue = '0.00';
        $budgetedExpenses = '0.00';

        foreach ($budgets as $budget) {
            $code = $budget->account->code ?? '';
            $annualBudget = bcmul((string) $budget->monthly_amount, '12', 2);

            if (AccountCode::isRevenue($code)) {
                $budgetedRevenue = bcadd($budgetedRevenue, $annualBudget, 2);
            } elseif (AccountCode::isExpense($code)) {
                $budgetedExpenses = bcadd($budgetedExpenses, $annualBudget, 2);
            }
        }

        // Actual YTD figures are already in the main metrics
        $actualRevenue = $this->invoiceService->yearlyRevenue($organizationId, $year);
        $actualExpenses = $this->expenseService->yearlyTotal($organizationId, $year);

        // Pro-rated budget based on months elapsed
        $proRatedRevenue = bcmul(bcdiv($budgetedRevenue, '12', 6), (string) $monthsElapsed, 2);
        $proRatedExpenses = bcmul(bcdiv($budgetedExpenses, '12', 6), (string) $monthsElapsed, 2);

        return [
            'budgetedRevenue' => $budgetedRevenue,
            'budgetedExpenses' => $budgetedExpenses,
            'proRatedRevenue' => $proRatedRevenue,
            'proRatedExpenses' => $proRatedExpenses,
            'actualRevenue' => $actualRevenue,
            'actualExpenses' => $actualExpenses,
            'revenueVariance' => bccomp($proRatedRevenue, '0', 2) !== 0
                ? bcmul(bcdiv(bcsub($actualRevenue, $proRatedRevenue, 2), $proRatedRevenue, 4), '100', 1)
                : '0.0',
            'expenseVariance' => bccomp($proRatedExpenses, '0', 2) !== 0
                ? bcmul(bcdiv(bcsub($actualExpenses, $proRatedExpenses, 2), $proRatedExpenses, 4), '100', 1)
                : '0.0',
            'monthsElapsed' => $monthsElapsed,
        ];
    }

    /**
     * Current-quarter VAT liability summary.
     *
     * Returns null when no VAT entries exist for the quarter.
     *
     * @return array{vatPayable: string, quarterLabel: string, quarterEnd: string}|null
     */
    private function currentQuarterVat(string $organizationId): ?array
    {
        $now = now();
        $quarter = (int) ceil($now->month / 3);
        $fromMonth = ($quarter - 1) * 3 + 1;
        $from = Carbon::create($now->year, $fromMonth, 1)->toDateString();
        $to = Carbon::create($now->year, $fromMonth, 1)->endOfQuarter()->toDateString();

        $report = $this->vatReportService->generate($organizationId, $from, $to);

        // No VAT activity this quarter
        if ($report['total_revenue'] === '0.00' && $report['input_vat'] === '0.00') {
            return null;
        }

        return [
            'vatPayable' => $report['vat_payable'],
            'quarterLabel' => 'Q'.$quarter.' '.$now->year,
            'quarterEnd' => $to,
        ];
    }

    /**
     * Receivables aging summary with overdue totals per bracket.
     *
     * Returns null when there are no overdue receivables.
     *
     * @return array{overdueCount: int, totalOverdue: string, brackets: array<string, string>}|null
     */
    private function agingSummary(string $organizationId): ?array
    {
        $report = $this->agingReportService->generate($organizationId, 'receivables');

        $overdueBrackets = ['1_30', '31_60', '61_90', '90_plus'];
        $totalOverdue = '0.00';
        $overdueCount = 0;
        $bracketTotals = [];

        foreach ($overdueBrackets as $key) {
            $bracket = $report['brackets'][$key];
            $totalOverdue = bcadd($totalOverdue, $bracket['total'], 2);
            $overdueCount += count($bracket['items']);
            $bracketTotals[$key] = $bracket['total'];
        }

        if (bccomp($totalOverdue, '0', 2) === 0) {
            return null;
        }

        return [
            'overdueCount' => $overdueCount,
            'totalOverdue' => $totalOverdue,
            'brackets' => $bracketTotals,
        ];
    }
}
