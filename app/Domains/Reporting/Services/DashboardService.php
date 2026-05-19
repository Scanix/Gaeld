<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Queries\InvoiceReportingQuery;
use App\Domains\Organizations\Models\Organization;
use App\Support\Money;
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
     * @return array<string, mixed>
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

    /**
     * @return array<string, mixed>
     */
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
        $hasPreviousYearData = $this->hasActivityInYear($organizationId, $year - 1);

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
            'balance' => Money::subtract($totalRevenue, $totalExpenses),
            'previousRevenue' => $previousRevenue,
            'previousExpenses' => $previousExpenses,
            'previousBalance' => Money::subtract($previousRevenue, $previousExpenses),
            'hasPreviousYearData' => $hasPreviousYearData,
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

    /**
     * @return Collection<int, mixed>
     */
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
        $hasRevenue = $entry->lines->contains(fn ($line) => AccountCode::isRevenue($line->account->code ?? ''));

        if ($hasRevenue) {
            return 'income';
        }

        $hasExpense = $entry->lines->contains(fn ($line) => AccountCode::isExpense($line->account->code ?? ''));

        return $hasExpense ? 'expense' : 'transfer';
    }

    /**
     * @return array<string, mixed>
     */
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

        $monthsElapsed = $this->computeMonthsElapsed($organizationId, $year);

        $budgetedRevenue = '0.00';
        $budgetedExpenses = '0.00';

        foreach ($budgets as $budget) {
            $code = $budget->account->code ?? '';
            $annualBudget = Money::multiply2((string) $budget->monthly_amount, '12');

            if (AccountCode::isRevenue($code)) {
                $budgetedRevenue = Money::add($budgetedRevenue, $annualBudget);
            } elseif (AccountCode::isExpense($code)) {
                $budgetedExpenses = Money::add($budgetedExpenses, $annualBudget);
            }
        }

        // Actual YTD figures are already in the main metrics
        $actualRevenue = $this->invoiceQuery->yearlyRevenue($organizationId, $year);
        $actualExpenses = $this->expenseService->yearlyTotal($organizationId, $year);

        // Pro-rated budget based on months elapsed
        $proRatedRevenue = Money::multiply2(Money::divide4($budgetedRevenue, '12'), (string) $monthsElapsed);
        $proRatedExpenses = Money::multiply2(Money::divide4($budgetedExpenses, '12'), (string) $monthsElapsed);

        return [
            'budgetedRevenue' => $budgetedRevenue,
            'budgetedExpenses' => $budgetedExpenses,
            'proRatedRevenue' => $proRatedRevenue,
            'proRatedExpenses' => $proRatedExpenses,
            'actualRevenue' => $actualRevenue,
            'actualExpenses' => $actualExpenses,
            'revenueVariance' => ! Money::isZero($proRatedRevenue)
                ? Money::multiply2(Money::divide4(Money::subtract($actualRevenue, $proRatedRevenue), $proRatedRevenue), '100')
                : '0.00',
            'expenseVariance' => ! Money::isZero($proRatedExpenses)
                ? Money::multiply2(Money::divide4(Money::subtract($actualExpenses, $proRatedExpenses), $proRatedExpenses), '100')
                : '0.00',
            'monthsElapsed' => $monthsElapsed,
        ];
    }

    /**
     * Compute months elapsed for a given fiscal year, respecting org fiscal_year_start.
     *
     * For past years returns the full duration (typically 12 months, but can be
     * up to 23 for Swiss long fiscal years). For the current fiscal year returns
     * the number of months elapsed since its start.
     */
    private function computeMonthsElapsed(string $organizationId, int $year): int
    {
        // Prefer a record from the fiscal_years table (handles long fiscal years).
        $fiscalYear = FiscalYear::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->forDate(Carbon::create($year, 6, 30)->toDateString())
            ->first();

        if ($fiscalYear !== null) {
            $fyStart = $fiscalYear->start_date->copy()->startOfDay();
            $fyEnd = $fiscalYear->end_date->copy()->endOfDay();
        } else {
            $org = Organization::find($organizationId);
            $startMonthDay = $org?->fiscal_year_start ?: '01-01';
            $parts = preg_split('/[\.\-\/]/', (string) $startMonthDay);
            $month = (int) ($parts[0] ?? 1);
            $day = (int) ($parts[1] ?? 1);
            if ($month < 1 || $month > 12) {
                $month = 1;
            }
            if ($day < 1 || $day > 31) {
                $day = 1;
            }

            $fyStart = Carbon::create($year, $month, $day)->startOfDay();
            $fyEnd = $fyStart->copy()->addYear()->subDay()->endOfDay();
        }

        $now = now();
        $totalMonths = (int) $fyStart->diffInMonths($fyEnd->copy()->addDay());

        if ($now->greaterThan($fyEnd)) {
            return max(1, $totalMonths);
        }

        if ($now->lessThan($fyStart)) {
            return 0;
        }

        return (int) $fyStart->diffInMonths($now->startOfMonth()) + 1;
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
            $totalOverdue = Money::add($totalOverdue, $bracket['total']);
            $overdueCount += count($bracket['items']);
            $bracketTotals[$key] = $bracket['total'];
        }

        if (Money::isZero($totalOverdue)) {
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

        // Invoice and expense dates are the real business-activity signals.
        // Journal entries are checked first in a naive implementation, but they
        // include technical bookkeeping entries (opening balances, year-end
        // closing) that can fall in the *next* calendar year — causing the
        // dashboard to display an empty year with all-zero KPIs immediately
        // after a year-end closing.
        $latestExpenseDate = Expense::where('organization_id', $organizationId)->max('date');
        $latestInvoiceDate = Invoice::where('organization_id', $organizationId)->max('issue_date');

        $activityYears = array_filter([
            $latestExpenseDate ? (int) Carbon::parse($latestExpenseDate)->year : null,
            $latestInvoiceDate ? (int) Carbon::parse($latestInvoiceDate)->year : null,
        ]);

        if (! empty($activityYears)) {
            return min(max($activityYears), $currentYear);
        }

        // No invoice/expense activity yet — fall back to the most recent posted
        // journal entry (covers manual-journal-entry-only organisations).
        $latestDate = $this->ledgerService->latestPostedEntryDate($organizationId);

        if ($latestDate) {
            return min((int) Carbon::parse($latestDate)->year, $currentYear);
        }

        return $currentYear;
    }

    /**
     * Whether the organization recorded any invoice or expense activity
     * during the given calendar year. Used to suppress year-over-year
     * trend indicators when there is no real comparison baseline.
     */
    private function hasActivityInYear(string $organizationId, int $year): bool
    {
        $hasInvoice = Invoice::where('organization_id', $organizationId)
            ->whereYear('issue_date', $year)
            ->exists();

        if ($hasInvoice) {
            return true;
        }

        return Expense::where('organization_id', $organizationId)
            ->whereYear('date', $year)
            ->exists();
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
