<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;

class ReportingService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Generate a profit & loss statement.
     */
    public function profitAndLoss(string $organizationId, string $fromDate, string $toDate): array
    {
        $revenue = Account::where('organization_id', $organizationId)
            ->where('type', Account::TYPE_REVENUE)
            ->where('is_active', true)
            ->get()
            ->map(fn (Account $account) => [
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $this->ledgerService->accountBalance($account->id, $fromDate, $toDate),
            ])
            ->filter(fn ($item) => $item['balance'] != 0);

        $expenses = Account::where('organization_id', $organizationId)
            ->where('type', Account::TYPE_EXPENSE)
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
    }

    /**
     * Generate a balance sheet.
     */
    public function balanceSheet(string $organizationId, string $asOfDate): array
    {
        $types = [
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
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
            'assets' => $sections[Account::TYPE_ASSET],
            'liabilities' => $sections[Account::TYPE_LIABILITY],
            'equity' => $sections[Account::TYPE_EQUITY],
        ];
    }
}
