<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\AccountCode;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function getMetrics(string $organizationId): array
    {
        $year = now()->year;

        $totalRevenue = (float) Invoice::where('organization_id', $organizationId)
            ->where('status', 'paid')
            ->whereYear('issue_date', $year)
            ->sum('total');

        $totalExpenses = (float) Expense::where('organization_id', $organizationId)
            ->whereYear('date', $year)
            ->sum('amount');

        $bankAccount = Account::where('organization_id', $organizationId)
            ->where('code', AccountCode::BANK_CASH)
            ->first();

        $cashBalance = 0.0;
        if ($bankAccount) {
            $debits = (float) TransactionLine::where('account_id', $bankAccount->id)->sum('debit');
            $credits = (float) TransactionLine::where('account_id', $bankAccount->id)->sum('credit');
            $cashBalance = (float) bcsub((string) $debits, (string) $credits, 2);
        }

        $unpaidInvoices = Invoice::where('organization_id', $organizationId)
            ->whereIn('status', ['sent', 'overdue'])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        $pendingExpenses = Expense::where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        $recentTransactions = JournalEntry::where('organization_id', $organizationId)
            ->with('lines.account')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (JournalEntry $entry) {
                $hasRevenue = $entry->lines->contains(fn ($l) => str_starts_with($l->account?->code ?? '', '3'));
                $hasExpense = $entry->lines->contains(fn ($l) => in_array(substr($l->account?->code ?? '', 0, 1), ['4', '5', '6']));
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

        $monthlyData = collect(range(1, 12))->map(function ($month) use ($year, $organizationId) {
            $monthRevenue = (float) Invoice::where('organization_id', $organizationId)
                ->where('status', 'paid')
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->sum('total');

            $monthExpenses = (float) Expense::where('organization_id', $organizationId)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');

            $forecast = (float) Invoice::where('organization_id', $organizationId)
                ->whereIn('status', ['sent', 'overdue'])
                ->whereYear('due_date', $year)
                ->whereMonth('due_date', $month)
                ->sum('total');

            $revenueItems = Invoice::where('organization_id', $organizationId)
                ->where('status', 'paid')
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->select('number', 'total')
                ->get()
                ->map(fn ($i) => $i->number . ': ' . number_format((float) $i->total, 2, '.', "'"));

            $expenseItems = Expense::where('organization_id', $organizationId)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->select('description', 'amount')
                ->get()
                ->map(fn ($e) => $e->description . ': ' . number_format((float) $e->amount, 2, '.', "'"));

            $forecastItems = Invoice::where('organization_id', $organizationId)
                ->whereIn('status', ['sent', 'overdue'])
                ->whereYear('due_date', $year)
                ->whereMonth('due_date', $month)
                ->select('number', 'total')
                ->get()
                ->map(fn ($i) => $i->number . ': ' . number_format((float) $i->total, 2, '.', "'"));

            return [
                'month' => Carbon::create($year, $month, 1)->format('M'),
                'revenue' => $monthRevenue,
                'expenses' => $monthExpenses,
                'forecast' => $forecast,
                'revenueItems' => $revenueItems->values(),
                'expenseItems' => $expenseItems->values(),
                'forecastItems' => $forecastItems->values(),
            ];
        });

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
            'balance' => (float) bcsub((string) $totalRevenue, (string) $totalExpenses, 2),
            'recentTransactions' => $recentTransactions,
            'monthlyData' => [
                'labels' => $monthlyData->pluck('month')->values(),
                'revenue' => $monthlyData->pluck('revenue')->values(),
                'expenses' => $monthlyData->pluck('expenses')->values(),
                'forecast' => $monthlyData->pluck('forecast')->values(),
                'revenueItems' => $monthlyData->pluck('revenueItems')->values(),
                'expenseItems' => $monthlyData->pluck('expenseItems')->values(),
                'forecastItems' => $monthlyData->pluck('forecastItems')->values(),
            ],
        ];
    }
}
