<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Assets\Models\DepreciationEntry;
use App\Domains\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Generates financial reports: balance sheet, income statement,
 * trial balance, account statements, and budget variance analysis.
 */
class ReportingService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Generate a profit & loss statement, optionally with a comparison period.
     *
     * Results are cached per organization + period (tag: org:{orgId}:reports).
     *
     * @return array{period: array{from: string, to: string}, revenue: array<int, array{code: string, name: string, balance: string}>, expenses: array<int, array{code: string, name: string, balance: string}>, total_revenue: string, total_expenses: string, net_profit: string, comparison: array|null, variance: array|null, budget: array|null}
     */
    public function profitAndLoss(
        string $organizationId,
        string $fromDate,
        string $toDate,
        ?string $compareFrom = null,
        ?string $compareTo = null,
    ): array {
        $cacheKey = "pnl:{$organizationId}:{$fromDate}:{$toDate}";
        if ($compareFrom && $compareTo) {
            $cacheKey .= ":vs:{$compareFrom}:{$compareTo}";
        }
        $orgTag = "org:{$organizationId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $fromDate, $toDate, $compareFrom, $compareTo) {
            $revenue = $this->accountsWithBalances($organizationId, AccountType::Revenue, $fromDate, $toDate);
            $expenses = $this->accountsWithBalances($organizationId, AccountType::Expense, $fromDate, $toDate);

            $totalRevenue = $revenue->sum('balance');
            $totalExpenses = $expenses->sum('balance');

            $result = [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'revenue' => $revenue->values()->toArray(),
                'expenses' => $expenses->values()->toArray(),
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_profit' => bcsub((string) $totalRevenue, (string) $totalExpenses, 2),
                'comparison' => null,
                'variance' => null,
            ];

            if ($compareFrom && $compareTo) {
                $compRevenue = $this->accountsWithBalances($organizationId, AccountType::Revenue, $compareFrom, $compareTo);
                $compExpenses = $this->accountsWithBalances($organizationId, AccountType::Expense, $compareFrom, $compareTo);

                $compTotalRevenue = $compRevenue->sum('balance');
                $compTotalExpenses = $compExpenses->sum('balance');
                $compNetProfit = bcsub((string) $compTotalRevenue, (string) $compTotalExpenses, 2);

                $result['comparison'] = [
                    'period' => ['from' => $compareFrom, 'to' => $compareTo],
                    'revenue' => $compRevenue->values()->toArray(),
                    'expenses' => $compExpenses->values()->toArray(),
                    'total_revenue' => $compTotalRevenue,
                    'total_expenses' => $compTotalExpenses,
                    'net_profit' => $compNetProfit,
                ];

                $revenueVariance = bcsub((string) $totalRevenue, (string) $compTotalRevenue, 2);
                $expenseVariance = bcsub((string) $totalExpenses, (string) $compTotalExpenses, 2);
                $netProfitVariance = bcsub($result['net_profit'], $compNetProfit, 2);

                $result['variance'] = [
                    'total_revenue' => [
                        'amount' => $revenueVariance,
                        'percentage' => bccomp((string) $compTotalRevenue, '0', 2) !== 0
                            ? bcmul(bcdiv($revenueVariance, (string) $compTotalRevenue, 4), '100', 2)
                            : null,
                    ],
                    'total_expenses' => [
                        'amount' => $expenseVariance,
                        'percentage' => bccomp((string) $compTotalExpenses, '0', 2) !== 0
                            ? bcmul(bcdiv($expenseVariance, (string) $compTotalExpenses, 4), '100', 2)
                            : null,
                    ],
                    'net_profit' => [
                        'amount' => $netProfitVariance,
                        'percentage' => bccomp($compNetProfit, '0', 2) !== 0
                            ? bcmul(bcdiv($netProfitVariance, $compNetProfit, 4), '100', 2)
                            : null,
                    ],
                ];
            }

            // Budget enrichment
            $result['budget'] = $this->enrichWithBudget($organizationId, $fromDate, $toDate, $result);

            return $result;
        });
    }

    /**
     * Generate a balance sheet.
     *
     * Includes the current-year net income (revenue − expenses) as a synthetic
     * equity row so that Assets = Liabilities + Equity always holds.
     *
     * Results are cached per organization + as-of date (tag: org:{orgId}:reports).
     *
     * @return array{as_of_date: string, assets: array{accounts: array<int, array{code: string, name: string, balance: string}>, total: mixed}, liabilities: array{accounts: array<int, array{code: string, name: string, balance: string}>, total: mixed}, equity: array{accounts: array<int, array{code: string, name: string, balance: string}>, total: mixed}}
     */
    public function balanceSheet(string $organizationId, string $asOfDate): array
    {
        $cacheKey = "bs:{$organizationId}:{$asOfDate}";
        $orgTag = "org:{$organizationId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $asOfDate) {
            $types = [
                AccountType::Asset,
                AccountType::Liability,
                AccountType::Equity,
            ];

            $sections = [];

            foreach ($types as $type) {
                $accounts = $this->accountsWithBalances($organizationId, $type, null, $asOfDate);

                $sections[$type->value] = [
                    'accounts' => $accounts->values()->toArray(),
                    'total' => $accounts->sum('balance'),
                ];
            }

            // Compute current-year net income (revenue − expenses) and add it
            // as a synthetic row in the equity section so the balance sheet balances.
            $fiscalYearStart = $this->resolveFiscalYearStart($organizationId, $asOfDate);
            $revenue = $this->accountsWithBalances($organizationId, AccountType::Revenue, $fiscalYearStart, $asOfDate);
            $expenses = $this->accountsWithBalances($organizationId, AccountType::Expense, $fiscalYearStart, $asOfDate);
            $currentYearResult = bcsub((string) $revenue->sum('balance'), (string) $expenses->sum('balance'), 2);

            if (bccomp($currentYearResult, '0', 2) !== 0) {
                $sections[AccountType::Equity->value]['accounts'][] = [
                    'code' => '2990',
                    'name' => __('app.current_year_result'),
                    'balance' => $currentYearResult,
                ];
                $sections[AccountType::Equity->value]['total'] = bcadd(
                    (string) $sections[AccountType::Equity->value]['total'],
                    $currentYearResult,
                    2,
                );
            }

            return [
                'as_of_date' => $asOfDate,
                'assets' => $sections[AccountType::Asset->value],
                'liabilities' => $sections[AccountType::Liability->value],
                'equity' => $sections[AccountType::Equity->value],
            ];
        });
    }

    /**
     * Determine the fiscal year start date for the organization containing the given as-of date.
     *
     * Uses the organization's `fiscal_year_start` (MM-DD) if set, otherwise defaults to Jan 1.
     */
    private function resolveFiscalYearStart(string $organizationId, string $asOfDate): string
    {
        $org = Organization::findOrFail($organizationId);
        $asOf = Carbon::parse($asOfDate);

        // fiscal_year_start is stored as "MM.DD" or "MM-DD" (e.g. "01.01")
        $startMonthDay = $org->fiscal_year_start ?? '01.01';
        $parts = preg_split('/[\.\-\/]/', $startMonthDay);
        $month = (int) ($parts[0] ?? 1);
        $day = (int) ($parts[1] ?? 1);

        $fiscalStart = Carbon::create($asOf->year, $month, $day);

        // If the fiscal year start is after the as-of date, go back one year
        if ($fiscalStart->gt($asOf)) {
            $fiscalStart->subYear();
        }

        return $fiscalStart->toDateString();
    }

    private function accountsWithBalances(string $organizationId, AccountType $type, ?string $fromDate, ?string $toDate): Collection
    {
        return Account::where('organization_id', $organizationId)
            ->where('type', $type->value)
            ->where('is_active', true)
            ->get()
            ->map(fn (Account $account) => [
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $this->ledgerService->accountBalance($account->id, $fromDate, $toDate),
            ])
            ->filter(fn ($accountRow) => bccomp((string) $accountRow['balance'], '0', 2) !== 0);
    }

    /**
     * Enrich P&L result with budget data if budgets exist for the period.
     */
    private function enrichWithBudget(string $organizationId, string $fromDate, string $toDate, array &$result): ?array
    {
        $fromCarbon = Carbon::parse($fromDate);
        $toCarbon = Carbon::parse($toDate);
        $fiscalYear = $fromCarbon->year;

        $budgets = Budget::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->forYear($fiscalYear)
            ->get()
            ->keyBy('account_id');

        if ($budgets->isEmpty()) {
            return null;
        }

        // Calculate number of months in the period
        $months = (int) $fromCarbon->diffInMonths($toCarbon) + 1;

        $accountIdsByCode = Account::where('organization_id', $organizationId)
            ->pluck('id', 'code');

        $result['revenue'] = $this->applyBudgetToAccounts($result['revenue'], $budgets, $accountIdsByCode, $months);
        $result['expenses'] = $this->applyBudgetToAccounts($result['expenses'], $budgets, $accountIdsByCode, $months);

        $budgetRevenue = '0.00';
        $budgetExpenses = '0.00';

        foreach ($result['revenue'] as $account) {
            if ($account['budget_amount'] !== null) {
                $budgetRevenue = bcadd($budgetRevenue, $account['budget_amount'], 2);
            }
        }
        foreach ($result['expenses'] as $account) {
            if ($account['budget_amount'] !== null) {
                $budgetExpenses = bcadd($budgetExpenses, $account['budget_amount'], 2);
            }
        }

        return [
            'total_revenue' => $budgetRevenue,
            'total_expenses' => $budgetExpenses,
            'net_profit' => bcsub($budgetRevenue, $budgetExpenses, 2),
            'months' => $months,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Cash Flow Statement (indirect method)
    // ──────────────────────────────────────────────────────────────

    /**
     * Generate a cash flow statement using the indirect method.
     *
     * Starts from net income, then adjusts for non-cash items and
     * working capital changes (AR, AP), then investing and financing.
     *
     * Cash account = 1020 (Bank/Cash).
     * AR             = 1100
     * AP             = 2000
     * Fixed assets   = prefix 15xx (1500–1599)
     * Equity         = prefix 28xx (2800+)
     * Long-term liab = prefix 24xx (2400–2499)
     * Depreciation accounts handled via DepreciationEntry if present.
     */
    public function cashFlow(string $organizationId, string $fromDate, string $toDate): array
    {
        $cacheKey = "cashflow:{$organizationId}:{$fromDate}:{$toDate}";
        $orgTag = "org:{$organizationId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $fromDate, $toDate) {
            // Net income from P&L
            $pnl = $this->profitAndLoss($organizationId, $fromDate, $toDate);
            $netIncome = $pnl['net_profit'];

            // "beginning" = up to the day before fromDate
            $beginDate = Carbon::parse($fromDate)->subDay()->toDateString();

            // ── Operating Activities ──────────────────────────────
            // Depreciation: sum DepreciationEntry amounts in period (non-cash add-back)
            $depreciationTotal = '0.00';
            if (class_exists(DepreciationEntry::class)) {
                $depreciationTotal = (string) DepreciationEntry::whereHas('fixedAsset', fn ($q) => $q->where('organization_id', $organizationId))
                    ->whereBetween('period_date', [$fromDate, $toDate])
                    ->sum('amount');
                $depreciationTotal = bcadd($depreciationTotal, '0', 2); // normalize to 2dp string
            }

            // AR change (increase in AR = cash decrease)
            $arDelta = $this->accountBalanceDelta($organizationId, '1100', $beginDate, $toDate);
            $arAdjustment = bcmul($arDelta, '-1', 2); // negate: AR up → less cash

            // AP change (increase in AP = cash increase)
            $apDelta = $this->accountBalanceDelta($organizationId, '2000', $beginDate, $toDate);
            $apAdjustment = $apDelta; // AP up → more cash

            $operatingAdjustments = [];

            if (bccomp($depreciationTotal, '0', 2) !== 0) {
                $operatingAdjustments[] = ['label' => 'Depreciation (add-back)', 'amount' => $depreciationTotal];
            }
            if (bccomp($arAdjustment, '0', 2) !== 0) {
                $operatingAdjustments[] = ['label' => 'Change in Accounts Receivable', 'amount' => $arAdjustment];
            }
            if (bccomp($apAdjustment, '0', 2) !== 0) {
                $operatingAdjustments[] = ['label' => 'Change in Accounts Payable', 'amount' => $apAdjustment];
            }

            $operatingAdjTotal = array_reduce($operatingAdjustments, fn ($c, $a) => bcadd($c, $a['amount'], 2), '0.00');
            $operatingTotal = bcadd($netIncome, $operatingAdjTotal, 2);

            // ── Investing Activities ──────────────────────────────
            // Changes in fixed asset accounts (1500–1599): increase = cash outflow
            $investingItems = [];
            $fixedAssetAccounts = Account::where('organization_id', $organizationId)
                ->where('code', 'like', '15%')
                ->where('is_active', true)
                ->get();

            foreach ($fixedAssetAccounts as $account) {
                $begin = $this->ledgerService->accountBalance($account->id, null, $beginDate);
                $end = $this->ledgerService->accountBalance($account->id, null, $toDate);
                $delta = bcsub($end, $begin, 2);
                if (bccomp($delta, '0', 2) !== 0) {
                    // Asset increase = cash outflow (negate)
                    $investingItems[] = ['label' => "Change in {$account->name}", 'amount' => bcmul($delta, '-1', 2)];
                }
            }

            $investingTotal = array_reduce($investingItems, fn ($c, $a) => bcadd($c, $a['amount'], 2), '0.00');

            // ── Financing Activities ──────────────────────────────
            $financingItems = [];

            // Equity changes (2800+)
            $equityAccounts = Account::where('organization_id', $organizationId)
                ->where('code', 'like', '28%')
                ->where('is_active', true)
                ->get();

            foreach ($equityAccounts as $account) {
                $begin = $this->ledgerService->accountBalance($account->id, null, $beginDate);
                $end = $this->ledgerService->accountBalance($account->id, null, $toDate);
                $delta = bcsub($end, $begin, 2);
                if (bccomp($delta, '0', 2) !== 0) {
                    $financingItems[] = ['label' => "Change in {$account->name}", 'amount' => $delta];
                }
            }

            // Long-term liability changes (24xx)
            $ltLiabAccounts = Account::where('organization_id', $organizationId)
                ->where('code', 'like', '24%')
                ->where('is_active', true)
                ->get();

            foreach ($ltLiabAccounts as $account) {
                $begin = $this->ledgerService->accountBalance($account->id, null, $beginDate);
                $end = $this->ledgerService->accountBalance($account->id, null, $toDate);
                $delta = bcsub($end, $begin, 2);
                if (bccomp($delta, '0', 2) !== 0) {
                    $financingItems[] = ['label' => "Change in {$account->name}", 'amount' => $delta];
                }
            }

            $financingTotal = array_reduce($financingItems, fn ($c, $a) => bcadd($c, $a['amount'], 2), '0.00');

            // ── Cash Summary ──────────────────────────────────────
            $netChange = bcadd(bcadd($operatingTotal, $investingTotal, 2), $financingTotal, 2);

            $cashAccount = Account::where('organization_id', $organizationId)
                ->where('code', '1020')
                ->first();

            $beginningCash = $cashAccount
                ? $this->ledgerService->accountBalance($cashAccount->id, null, $beginDate)
                : '0.00';

            // Reconcile against actual ending cash balance to catch untracked
            // movements (e.g. prepaid expenses, other current items not
            // explicitly handled above).
            $actualEndingCash = $cashAccount
                ? $this->ledgerService->accountBalance($cashAccount->id, null, $toDate)
                : '0.00';

            $reconciliationDiff = bcsub($actualEndingCash, bcadd($beginningCash, $netChange, 2), 2);

            if (bccomp($reconciliationDiff, '0', 2) !== 0) {
                $operatingAdjustments[] = ['label' => 'Other operating changes', 'amount' => $reconciliationDiff];
                $operatingAdjTotal = bcadd($operatingAdjTotal, $reconciliationDiff, 2);
                $operatingTotal = bcadd($operatingTotal, $reconciliationDiff, 2);
                $netChange = bcadd($netChange, $reconciliationDiff, 2);
            }

            $endingCash = bcadd($beginningCash, $netChange, 2);

            return [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'net_income' => $netIncome,
                'operating' => ['adjustments' => $operatingAdjustments, 'total' => $operatingTotal],
                'investing' => ['items' => $investingItems, 'total' => $investingTotal],
                'financing' => ['items' => $financingItems, 'total' => $financingTotal],
                'net_change' => $netChange,
                'beginning_cash' => $beginningCash,
                'ending_cash' => $endingCash,
            ];
        });
    }

    /**
     * Get the change in an account's balance between a beginning date and an end date.
     */
    private function accountBalanceDelta(string $organizationId, string $code, string $beginDate, string $toDate): string
    {
        $account = Account::where('organization_id', $organizationId)
            ->where('code', $code)
            ->first();

        if (! $account) {
            return '0.00';
        }

        $begin = $this->ledgerService->accountBalance($account->id, null, $beginDate);
        $end = $this->ledgerService->accountBalance($account->id, null, $toDate);

        return bcsub($end, $begin, 2);
    }

    /**
     * Annotate each account row with budget amount, variance, and variance percentage.
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  Collection<string, Budget>  $budgets
     * @param  Collection<string, string>  $accountIdsByCode
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    private function applyBudgetToAccounts(array $accounts, $budgets, $accountIdsByCode, int $months): array
    {
        return array_map(function (array $account) use ($budgets, $accountIdsByCode, $months): array {
            $accountId = $accountIdsByCode[$account['code']] ?? null;
            $budget = $accountId ? $budgets->get($accountId) : null;

            if ($budget) {
                $budgetAmount = bcmul((string) $budget->monthly_amount, (string) $months, 2);
                $variance = bcsub((string) $account['balance'], $budgetAmount, 2);
                $account['budget_amount'] = $budgetAmount;
                $account['budget_variance'] = $variance;
                $account['budget_variance_percentage'] = bccomp($budgetAmount, '0', 2) !== 0
                    ? bcmul(bcdiv($variance, $budgetAmount, 4), '100', 2)
                    : null;
            } else {
                $account['budget_amount'] = null;
                $account['budget_variance'] = null;
                $account['budget_variance_percentage'] = null;
            }

            return $account;
        }, $accounts);
    }
}
