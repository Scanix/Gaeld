<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Invoicing\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly InvoiceService $invoiceService,
        private readonly ExpenseService $expenseService,
    ) {}
    /**
     * @return array{revenue: string, expenses: string, cashBalance: string, unpaidInvoices: array{count: int, total: string}, pendingExpenses: array{count: int, total: string}, balance: string, recentTransactions: \Illuminate\Support\Collection, monthlyBreakdown: array}
     */
    public function metrics(string $organizationId): array
    {
        $year = now()->year;

        $totalRevenue = $this->invoiceService->yearlyRevenue($organizationId, $year);
        $totalExpenses = $this->expenseService->yearlyTotal($organizationId, $year);
        $cashBalance = $this->cashBalance($organizationId);

        $unpaidInvoices = $this->invoiceService->unpaidSummary($organizationId);

        $pendingExpenses = $this->expenseService->pendingSummary($organizationId);

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
            'recentTransactions' => $this->recentTransactions($organizationId),
            'monthlyBreakdown' => $this->monthlyBreakdown($organizationId, $year),
        ];
    }

    private function cashBalance(string $organizationId): string
    {
        try {
            $bankAccount = $this->ledgerService->resolveAccount($organizationId, AccountCode::BANK_CASH);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
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
