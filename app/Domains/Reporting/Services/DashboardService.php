<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Invoicing\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly InvoiceService $invoiceService,
    ) {}
    /**
     * @return array{revenue: float, expenses: float, cashBalance: string, unpaidInvoices: array{count: int, total: float}, pendingExpenses: array{count: int, total: float}, balance: float, recentTransactions: \Illuminate\Support\Collection, monthlyData: array}
     */
    public function metrics(string $organizationId): array
    {
        $year = now()->year;

        $totalRevenue = $this->invoiceService->yearlyRevenue($organizationId, $year);
        $totalExpenses = ExpenseQuery::yearlyTotal($organizationId, $year);
        $cashBalance = $this->cashBalance($organizationId);

        $unpaidInvoices = $this->invoiceService->unpaidSummary($organizationId);

        $pendingExpenses = ExpenseQuery::pendingSummary($organizationId);

        return [
            'revenue' => $totalRevenue,
            'expenses' => $totalExpenses,
            'cashBalance' => $cashBalance,
            'unpaidInvoices' => [
                'count' => $unpaidInvoices->count,
                'total' => (float) $unpaidInvoices->total,
            ],
            'pendingExpenses' => [
                'count' => $pendingExpenses->count,
                'total' => (float) $pendingExpenses->total,
            ],
            'balance' => $totalRevenue - $totalExpenses,
            'recentTransactions' => $this->recentTransactions($organizationId),
            'monthlyData' => $this->monthlyBreakdown($organizationId, $year),
        ];
    }

    private function cashBalance(string $organizationId): string
    {
        $bankAccount = Account::where('organization_id', $organizationId)
            ->where('code', AccountCode::BANK_CASH)
            ->first();

        if (! $bankAccount) {
            return '0.00';
        }

        return $this->ledgerService->accountBalance($bankAccount->id);
    }

    private function recentTransactions(string $organizationId): Collection
    {
        return JournalEntry::where('organization_id', $organizationId)
            ->with('lines.account')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (JournalEntry $entry) {
                $hasRevenue = $entry->lines->contains(fn ($l) => AccountCode::isRevenue($l->account?->code ?? ''));
                $hasExpense = $entry->lines->contains(fn ($l) => AccountCode::isExpense($l->account?->code ?? ''));
                $type = $hasRevenue ? 'income' : ($hasExpense ? 'expense' : 'transfer');

                return [
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'description' => $entry->description,
                    'reference' => $entry->reference,
                    'amount' => (float) $entry->lines->sum('debit'),
                    'type' => $type,
                ];
            });
    }

    private function monthlyBreakdown(string $organizationId, int $year): array
    {
        // Fetch all data for the year in 3 queries instead of 6*12
        $paidInvoices = $this->invoiceService->paidInYear($organizationId, $year)
            ->groupBy(fn ($i) => Carbon::parse($i->issue_date)->month);

        $expenses = ExpenseQuery::inYear($organizationId, $year)
            ->groupBy(fn ($e) => Carbon::parse($e->date)->month);

        $forecastInvoices = $this->invoiceService->sentOrOverdueDueInYear($organizationId, $year)
            ->groupBy(fn ($i) => Carbon::parse($i->due_date)->month);

        $monthlyData = collect(range(1, 12))->map(function ($month) use ($year, $paidInvoices, $expenses, $forecastInvoices) {
            $monthPaid = $paidInvoices->get($month, collect());
            $monthExpenses = $expenses->get($month, collect());
            $monthForecast = $forecastInvoices->get($month, collect());

            return [
                'month' => Carbon::create($year, $month, 1)->format('M'),
                'revenue' => (float) $monthPaid->sum('total'),
                'expenses' => (float) $monthExpenses->sum('amount'),
                'forecast' => (float) $monthForecast->sum('total'),
                'revenueItems' => $monthPaid->map(fn ($i) => $i->number . ': ' . number_format((float) $i->total, 2, '.', "'"))->values(),
                'expenseItems' => $monthExpenses->map(fn ($e) => $e->description . ': ' . number_format((float) $e->amount, 2, '.', "'"))->values(),
                'forecastItems' => $monthForecast->map(fn ($i) => $i->number . ': ' . number_format((float) $i->total, 2, '.', "'"))->values(),
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
}
