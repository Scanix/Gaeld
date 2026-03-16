<?php

namespace App\Http\Controllers;

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
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(! $orgId, 403, 'No organization found.');

        $year = now()->year;
        $startOfYear = Carbon::create($year, 1, 1)->toDateString();
        $today = now()->toDateString();

        $totalRevenue = (float) Invoice::where('organization_id', $orgId)
            ->where('status', 'paid')
            ->whereYear('issue_date', $year)
            ->sum('total');

        $totalExpenses = (float) Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->sum('amount');

        $recentTransactions = TransactionLine::whereHas('journalEntry', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })
            ->with('account', 'journalEntry')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($line) => [
                'id' => $line->id,
                'date' => $line->journalEntry->date,
                'description' => $line->journalEntry->description ?? $line->description,
                'account' => $line->account?->name,
                'debit' => $line->debit,
                'credit' => $line->credit,
            ]);

        // Monthly revenue vs expenses for Chart.js
        $monthlyData = collect(range(1, 12))->map(function ($month) use ($orgId, $year) {
            $monthRevenue = (float) Invoice::where('organization_id', $orgId)
                ->where('status', 'paid')
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->sum('total');

            $monthExpenses = (float) Expense::where('organization_id', $orgId)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->sum('amount');

            return [
                'month' => Carbon::create($year, $month, 1)->format('M'),
                'revenue' => $monthRevenue,
                'expenses' => $monthExpenses,
            ];
        });

        return Inertia::render('Dashboard', [
            'revenue' => $totalRevenue,
            'expenses' => $totalExpenses,
            'balance' => (float) bcsub((string) $totalRevenue, (string) $totalExpenses, 2),
            'transactionCount' => TransactionLine::whereHas('journalEntry', fn ($q) => $q->where('organization_id', $orgId))->count(),
            'recentTransactions' => $recentTransactions,
            'monthlyData' => $monthlyData,
        ]);
    }
}
