<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\ValueObjects\AccountCode;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array{revenue: float, expenses: float, cashBalance: string, unpaidInvoices: array{count: int, total: float}, pendingExpenses: array{count: int, total: float}, balance: float, recentTransactions: \Illuminate\Support\Collection, monthlyData: array}
     */
    public function getMetrics(string $organizationId): array
    {
        $year = now()->year;

        $totalRevenue = $this->yearlyInvoiceTotal($organizationId, $year);
        $totalExpenses = $this->yearlyExpenseTotal($organizationId, $year);
        $cashBalance = $this->cashBalance($organizationId);

        $unpaidInvoices = Invoice::where('organization_id', $organizationId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        $pendingExpenses = Expense::where('organization_id', $organizationId)
            ->where('status', ExpenseStatus::Pending)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return [
            'revenue' => $totalRevenue,
            'expenses' => $totalExpenses,
            'cashBalance' => $cashBalance,
            'unpaidInvoices' => [
                'count' => (int) ($unpaidInvoices->count ?? 0),
                'total' => (float) ($unpaidInvoices->total ?? 0),
            ],
            'pendingExpenses' => [
                'count' => (int) ($pendingExpenses->count ?? 0),
                'total' => (float) ($pendingExpenses->total ?? 0),
            ],
            'balance' => $totalRevenue - $totalExpenses,
            'recentTransactions' => $this->recentTransactions($organizationId),
            'monthlyData' => $this->monthlyBreakdown($organizationId, $year),
        ];
    }

    private function yearlyInvoiceTotal(string $organizationId, int $year): float
    {
        return (float) Invoice::where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('issue_date', $year)
            ->sum('total');
    }

    private function yearlyExpenseTotal(string $organizationId, int $year): float
    {
        return (float) Expense::where('organization_id', $organizationId)
            ->whereYear('date', $year)
            ->sum('amount');
    }

    private function cashBalance(string $organizationId): string
    {
        $bankAccount = Account::where('organization_id', $organizationId)
            ->where('code', AccountCode::BANK_CASH)
            ->first();

        if (! $bankAccount) {
            return '0.00';
        }

        $debits = TransactionLine::where('account_id', $bankAccount->id)->sum('debit');
        $credits = TransactionLine::where('account_id', $bankAccount->id)->sum('credit');

        return bcsub((string) $debits, (string) $credits, 2);
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
        $paidInvoices = Invoice::where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('issue_date', $year)
            ->select('number', 'total', 'issue_date')
            ->get()
            ->groupBy(fn ($i) => Carbon::parse($i->issue_date)->month);

        $expenses = Expense::where('organization_id', $organizationId)
            ->whereYear('date', $year)
            ->select('description', 'amount', 'date')
            ->get()
            ->groupBy(fn ($e) => Carbon::parse($e->date)->month);

        $forecastInvoices = Invoice::where('organization_id', $organizationId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereYear('due_date', $year)
            ->select('number', 'total', 'due_date')
            ->get()
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
