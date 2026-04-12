<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceReportingQuery;
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
        private readonly LedgerQueryService $ledgerService,
        private readonly InvoiceReportingQuery $invoiceQuery,
        private readonly ExpenseService $expenseService,
        private readonly VatReportService $vatReportService,
        private readonly AgingReportService $agingReportService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{revenue: string, expenses: string, cashBalance: string, unpaidInvoices: array{count: int, total: string}, pendingExpenses: array{count: int, total: string}, balance: string, recentTransactions: Collection, monthlyBreakdown: array, pendingOcrScans: int}
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
        $year = $this->resolveDisplayYear($organizationId);

        $totalRevenue = $this->invoiceQuery->yearlyRevenue($organizationId, $year);
        $totalExpenses = $this->expenseService->yearlyTotal($organizationId, $year);
        $cashBalance = $this->cashBalance($organizationId);

        $unpaidInvoices = $this->invoiceQuery->unpaidSummary($organizationId);

        $pendingExpenses = $this->expenseService->pendingSummary($organizationId);

        // Year-over-year comparison
        $previousRevenue = $this->invoiceQuery->yearlyRevenue($organizationId, $year - 1);
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
            'pendingOcrScans' => $this->pendingOcrScans($organizationId),
            'displayYear' => $year,
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
        $paidInvoices = $this->invoiceQuery->paidInYear($organizationId, $year)
            ->groupBy(fn ($i) => Carbon::parse($i->issue_date)->month);

        $expenses = $this->expenseService->inYear($organizationId, $year)
            ->groupBy(fn ($e) => Carbon::parse($e->date)->month);

        $forecastInvoices = $this->invoiceQuery->sentOrOverdueDueInYear($organizationId, $year)
            ->groupBy(fn ($i) => Carbon::parse($i->due_date)->month);

        $monthlyData = collect(range(1, 12))->map(function ($month) use ($paidInvoices, $expenses, $forecastInvoices) {
            $monthPaid = $paidInvoices->get($month, collect());
            $monthExpenses = $expenses->get($month, collect());
            $monthForecast = $forecastInvoices->get($month, collect());

            return [
                'monthIndex' => $month,
                'revenue' => (string) $monthPaid->sum('total'),
                'expenses' => (string) $monthExpenses->sum('amount'),
                'forecast' => (string) $monthForecast->sum('total'),
                'revenueItems' => $monthPaid->map(fn ($i) => $i->number.': '.number_format((float) $i->total, 2, '.', "'"))->values(),
                'expenseItems' => $monthExpenses->map(fn ($e) => $e->description.': '.number_format((float) $e->amount, 2, '.', "'"))->values(),
                'forecastItems' => $monthForecast->map(fn ($i) => $i->number.': '.number_format((float) $i->total, 2, '.', "'"))->values(),
            ];
        });

        return [
            'monthIndices' => $monthlyData->pluck('monthIndex')->values(),
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
        $actualRevenue = $this->invoiceQuery->yearlyRevenue($organizationId, $year);
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

    /**
     * Resolve the fiscal year to display on the dashboard.
     *
     * Priority order:
     * 1. Most recent year with posted journal entries (capped at current year).
     * 2. Most recent year with any expense or invoice activity (capped at current year).
     * 3. Current calendar year (no data at all).
     *
     * This prevents the dashboard from showing 0.00 CHF when an org has
     * expenses/invoices dated in a prior year but has not yet posted any
     * journal entries.
     */
    private function resolveDisplayYear(string $organizationId): int
    {
        $currentYear = now()->year;

        $latestDate = $this->ledgerService->latestPostedEntryDate($organizationId);

        if ($latestDate) {
            $latestYear = (int) Carbon::parse($latestDate)->year;

            // Never project into a future year; use the current year at most.
            return min($latestYear, $currentYear);
        }

        // No posted journal entries yet — fall back to the most recent year
        // that has any expense or invoice activity so the dashboard is useful.
        $latestExpenseDate = Expense::where('organization_id', $organizationId)->max('date');
        $latestInvoiceDate = Invoice::where('organization_id', $organizationId)->max('issue_date');

        $activityYears = array_filter([
            $latestExpenseDate ? (int) Carbon::parse($latestExpenseDate)->year : null,
            $latestInvoiceDate ? (int) Carbon::parse($latestInvoiceDate)->year : null,
        ]);

        if (! empty($activityYears)) {
            return min(max($activityYears), $currentYear);
        }

        return $currentYear;
    }

    private function pendingOcrScans(string $organizationId): int
    {
        return ReceiptScan::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['pending', 'completed'])
            ->where('expires_at', '>', now())
            ->count();
    }
}
