<?php

namespace App\Http\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $year = now()->year;

        $totalRevenue = (float) Invoice::where('status', 'paid')
            ->whereYear('issue_date', $year)
            ->sum('total');

        $totalExpenses = (float) Expense::whereYear('date', $year)
            ->sum('amount');

        // Cash balance from bank ledger account (1020)
        $bankAccount = Account::where('code', '1020')->first();

        $cashBalance = 0.0;
        if ($bankAccount) {
            $debits = (float) TransactionLine::where('account_id', $bankAccount->id)->sum('debit');
            $credits = (float) TransactionLine::where('account_id', $bankAccount->id)->sum('credit');
            $cashBalance = (float) bcsub((string) $debits, (string) $credits, 2);
        }

        // Unpaid invoices
        $unpaidInvoices = Invoice::whereIn('status', ['sent', 'overdue'])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        // Pending expenses
        $pendingExpenses = Expense::where('status', 'pending')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        // Recent transactions — one row per journal entry, classified by type
        $recentTransactions = JournalEntry::with('lines.account')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (JournalEntry $entry) {
                // Classify based on account codes touched
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

        // Monthly revenue vs expenses for Chart.js
        $monthlyData = collect(range(1, 12))->map(function ($month) use ($year) {
            $monthRevenue = (float) Invoice::where('status', 'paid')
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->sum('total');

            $monthExpenses = (float) Expense::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');

            // Forecast: sent/overdue invoices due this month (not yet paid)
            $forecast = (float) Invoice::whereIn('status', ['sent', 'overdue'])
                ->whereYear('due_date', $year)
                ->whereMonth('due_date', $month)
                ->sum('total');

            // Detail items for tooltip
            $revenueItems = Invoice::where('status', 'paid')
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->select('number', 'total')
                ->get()
                ->map(fn ($i) => $i->number . ': ' . number_format((float) $i->total, 2, '.', "'"));

            $expenseItems = Expense::whereYear('date', $year)
                ->whereMonth('date', $month)
                ->select('description', 'amount')
                ->get()
                ->map(fn ($e) => $e->description . ': ' . number_format((float) $e->amount, 2, '.', "'"));

            $forecastItems = Invoice::whereIn('status', ['sent', 'overdue'])
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

        return Inertia::render('Dashboard', [
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
        ]);
    }
}
