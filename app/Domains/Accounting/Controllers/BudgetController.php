<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $year = (int) $request->input('year', now()->year);

        $budgets = Budget::with('account:id,code,name,type')
            ->forYear($year)
            ->get();

        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['Revenue', 'Expense'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Accounting/Budgets', [
            'budgets' => $budgets,
            'accounts' => $accounts,
            'year' => $year,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        Budget::updateOrCreate(
            [
                'organization_id' => $currentOrg->id(),
                'account_id' => $validated['account_id'],
                'fiscal_year' => $validated['fiscal_year'],
            ],
            [
                'monthly_amount' => $validated['monthly_amount'],
            ],
        );

        return redirect()->route('accounting.budgets', ['year' => $validated['fiscal_year']])
            ->with('success', __('Budget saved successfully.'));
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', Account::class);

        $year = $budget->fiscal_year;
        $budget->delete();

        return redirect()->route('accounting.budgets', ['year' => $year])
            ->with('success', __('Budget deleted successfully.'));
    }
}
