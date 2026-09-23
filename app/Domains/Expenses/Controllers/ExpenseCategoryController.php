<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Expenses\Queries\ExpenseCategoryQuery;
use App\Domains\Expenses\Requests\StoreExpenseCategoryRequest;
use App\Domains\Expenses\Requests\UpdateExpenseCategoryRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ExpenseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        return response()->json(ExpenseCategoryQuery::all());
    }

    public function store(StoreExpenseCategoryRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $currentOrg->get());

        $validated = $request->validated();

        $maxSort = ExpenseCategory::where('organization_id', $currentOrg->id())->max('sort_order') ?? 0;

        ExpenseCategory::create([
            'organization_id' => $currentOrg->id(),
            ...$validated,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('settings', ['tab' => 'expenses'])
            ->with('success', __('app.expense_category_created'));
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $currentOrg->get());

        $expenseCategory->update($request->validated());

        return redirect()->route('settings', ['tab' => 'expenses'])
            ->with('success', __('app.expense_category_updated'));
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
        $defaultAccountId = Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('code', ExpenseCategory::RESALE_ACCOUNT_CODE)
            ->where('type', AccountType::Expense)
            ->where('is_active', true)
            ->value('id');

        foreach (ExpenseCategory::DEFAULT_CATEGORIES as $i => $name) {
            ExpenseCategory::withoutGlobalScopes()->create([
                'organization_id' => $organizationId,
                'name' => $name,
                'code' => ExpenseCategory::systemCodeFor($name),
                'translation_key' => ExpenseCategory::systemCodeFor($name)
                    ? 'cat_'.ExpenseCategory::systemCodeFor($name)
                    : null,
                'is_default' => true,
                'is_active' => true,
                'is_system' => ExpenseCategory::systemCodeFor($name) !== null,
                'sort_order' => $i,
                'default_expense_account_id' => $name === ExpenseCategory::RESALE_CATEGORY ? $defaultAccountId : null,
            ]);
        }
    }
}
