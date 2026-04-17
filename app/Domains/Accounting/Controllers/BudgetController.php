<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages monthly budget targets per account and fiscal year.
 */
class BudgetController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Budget::class);

        $year = (int) $request->input('year', now()->year);

        $budgets = Budget::with('account:id,code,name,type')
            ->forYear($year)
            ->paginate(25)
            ->withQueryString();

        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['Revenue', 'Expense'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $currentYear = now()->year;
        $fiscalYears = collect(range($currentYear, $currentYear - 4))
            ->map(fn (int $y) => ['value' => $y, 'label' => (string) $y])
            ->values()
            ->all();

        return Inertia::render('Accounting/Budgets/Index', [
            'budgets' => $budgets,
            'accounts' => $accounts,
            'fiscalYears' => $fiscalYears,
            'selectedYear' => $year,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Budget::class);

        $validated = $request->validate([
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $currentOrg->id())
                    ->where('is_active', true)
                ),
            ],
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
            ->with('success', __('app.budget_saved'));
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $year = $budget->fiscal_year;
        $budget->delete();

        return redirect()->route('accounting.budgets', ['year' => $year])
            ->with('success', __('app.budget_deleted'));
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('update', $budget);

        $validated = $request->validate([
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $budget->update($validated);

        return redirect()->route('accounting.budgets', ['year' => $budget->fiscal_year])
            ->with('success', __('app.budget_updated'));
    }
}
