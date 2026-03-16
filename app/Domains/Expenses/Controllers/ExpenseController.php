<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');
        $this->authorize('viewAny', Expense::class);

        $expenses = Expense::where('organization_id', $orgId)
            ->orderByDesc('date')
            ->paginate(20);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Expenses/Create');
    }

    public function store(Request $request, CreateExpenseAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => 'nullable|exists:vat_rates,id',
            'date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
        ]);

        $this->authorize('create', Expense::class);
        $validated['organization_id'] = $request->user()->resolveCurrentOrganization()->id;

        $expense = $action->execute($validated);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense created.');
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense->load(['vatRate', 'journalEntry.lines.account']),
        ]);
    }

    public function post(Expense $expense, Request $request, ExpenseService $service): RedirectResponse
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'expense_account_code' => 'required|string',
        ]);

        $service->postExpense($expense, $validated['expense_account_code']);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense posted to ledger.');
    }
}
