<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Expenses\Queries\ExpenseCategoryQuery;
use App\Domains\Expenses\Requests\StoreExpenseCategoryRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ExpenseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ExpenseCategoryQuery::all());
    }

    public function store(StoreExpenseCategoryRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $currentOrg->get());

        $validated = $request->validated();

        $maxSort = ExpenseCategory::query()->max('sort_order') ?? 0;

        ExpenseCategory::create([
            'organization_id' => $currentOrg->id(),
            'name' => $validated['name'],
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('settings', ['tab' => 'expenses'])
            ->with('success', __('app.expense_category_created'));
    }

    public function destroy(ExpenseCategory $expenseCategory, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $currentOrg->get());

        $expenseCategory->delete();

        return redirect()->route('settings', ['tab' => 'expenses'])
            ->with('success', __('app.expense_category_deleted'));
    }

    /**
     * Seed default categories for an organization that has none.
     */
    public static function seedDefaults(string $organizationId): void
    {
        foreach (ExpenseCategory::DEFAULT_CATEGORIES as $i => $name) {
            ExpenseCategory::withoutGlobalScopes()->create([
                'organization_id' => $organizationId,
                'name' => $name,
                'is_default' => true,
                'sort_order' => $i,
            ]);
        }
    }
}
