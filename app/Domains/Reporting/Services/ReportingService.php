<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use Illuminate\Support\Facades\Cache;

class ReportingService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Generate a profit & loss statement.
     *
     * Results are cached per organization + period (tag: org:{orgId}:reports).
     */
    public function profitAndLoss(string $organizationId, string $fromDate, string $toDate): array
    {
        $cacheKey = "pnl:{$organizationId}:{$fromDate}:{$toDate}";
        $orgTag = "org:{$organizationId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $fromDate, $toDate) {
            $revenue = Account::where('organization_id', $organizationId)
                ->where('type', AccountType::Revenue->value)
                ->where('is_active', true)
                ->get()
                ->map(fn (Account $account) => [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $this->ledgerService->accountBalance($account->id, $fromDate, $toDate),
                ])
                ->filter(fn ($item) => $item['balance'] != 0);

            $expenses = Account::where('organization_id', $organizationId)
                ->where('type', AccountType::Expense->value)
                ->where('is_active', true)
                ->get()
                ->map(fn (Account $account) => [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $this->ledgerService->accountBalance($account->id, $fromDate, $toDate),
                ])
                ->filter(fn ($item) => $item['balance'] != 0);

            $totalRevenue = $revenue->sum('balance');
            $totalExpenses = $expenses->sum('balance');

            return [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'revenue' => $revenue->values()->toArray(),
                'expenses' => $expenses->values()->toArray(),
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_profit' => (float) bcsub((string) $totalRevenue, (string) $totalExpenses, 2),
            ];
        });
    }

    /**
     * Generate a balance sheet.
     *
     * Results are cached per organization + as-of date (tag: org:{orgId}:reports).
     */
    public function balanceSheet(string $organizationId, string $asOfDate): array
    {
        $cacheKey = "bs:{$organizationId}:{$asOfDate}";
        $orgTag = "org:{$organizationId}:reports";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $asOfDate) {
            $types = [
                AccountType::Asset->value,
                AccountType::Liability->value,
                AccountType::Equity->value,
            ];

            $sections = [];

            foreach ($types as $type) {
                $accounts = Account::where('organization_id', $organizationId)
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn (Account $account) => [
                        'code' => $account->code,
                        'name' => $account->name,
                        'balance' => $this->ledgerService->accountBalance($account->id, null, $asOfDate),
                    ])
                    ->filter(fn ($item) => $item['balance'] != 0);

                $sections[$type] = [
                    'accounts' => $accounts->values()->toArray(),
                    'total' => $accounts->sum('balance'),
                ];
            }

            return [
                'as_of_date' => $asOfDate,
                'assets' => $sections[AccountType::Asset->value],
                'liabilities' => $sections[AccountType::Liability->value],
                'equity' => $sections[AccountType::Equity->value],
            ];
        });
    }
}
