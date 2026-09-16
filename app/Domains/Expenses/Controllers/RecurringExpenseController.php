<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Queries\AccountQuery;
use App\Domains\Contacts\Queries\ContactQuery;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Expenses\Models\RecurringExpense;
use App\Domains\Expenses\Queries\ExpenseCategoryQuery;
use App\Domains\Expenses\Requests\RecurringExpenseRequest;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for recurring expense schedules.
 */
class RecurringExpenseController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RecurringExpense::class);

        $recurringExpenses = RecurringExpense::with('supplier:id,name')
            ->orderByDesc('next_due_date')
            ->paginate(20);

        return Inertia::render('Expenses/Recurring/Index', [
            'recurringExpenses' => $recurringExpenses,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RecurringExpense::class);

        return Inertia::render('Expenses/Recurring/Create', [
            'suppliers' => ContactQuery::forSelect(),
            'categories' => ExpenseCategoryQuery::forSelect(),
            'expenseAccounts' => AccountQuery::forSelect(AccountType::Expense),
            'frequencies' => $this->frequencyOptions(),
        ]);
    }

    public function store(RecurringExpenseRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', RecurringExpense::class);

        $validated = $request->validated();
        $validated = $this->applyDefaultExpenseAccount($validated, $currentOrg->id());

        RecurringExpense::create([
            'organization_id' => $currentOrg->id(),
            'supplier_id' => $validated['supplier_id'] ?? null,
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'vat_amount' => $validated['vat_amount'] ?? 0,
            'vat_rate_id' => $validated['vat_rate_id'] ?? null,
            'vendor' => $validated['vendor'] ?? null,
            'currency' => $validated['currency'] ?? 'CHF',
            'payment_method' => $validated['payment_method'] ?? null,
            'expense_account_code' => $validated['expense_account_code'] ?? null,
            'bank_account_code' => $validated['bank_account_code'] ?? null,
            'frequency' => $validated['frequency'],
            'next_due_date' => $validated['next_due_date'],
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return redirect()->route('expenses.recurring.index')
            ->with('success', __('app.recurring_expense_created'));
    }

    public function edit(RecurringExpense $recurring): Response
    {
        $this->authorize('update', $recurring);

        return Inertia::render('Expenses/Recurring/Edit', [
            'recurringExpense' => $recurring->load('supplier:id,name'),
            'suppliers' => ContactQuery::forSelect(),
            'categories' => ExpenseCategoryQuery::forSelect(),
            'expenseAccounts' => AccountQuery::forSelect(AccountType::Expense),
            'frequencies' => $this->frequencyOptions(),
        ]);
    }

    public function update(RecurringExpenseRequest $request, RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $validated = array_merge([
            'expense_account_code' => $recurring->expense_account_code,
            'bank_account_code' => $recurring->bank_account_code,
        ], $request->validated());

        $recurring->update($this->applyDefaultExpenseAccount($validated, $recurring->organization_id));

        return redirect()->route('expenses.recurring.index')
            ->with('success', __('app.recurring_expense_updated'));
    }

    public function destroy(RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('delete', $recurring);

        $recurring->delete();

        return redirect()->route('expenses.recurring.index')
            ->with('success', __('app.recurring_expense_deleted'));
    }

    public function pause(RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $recurring->update(['is_active' => false]);

        return back()->with('success', __('app.recurring_paused'));
    }

    public function resume(RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);

        $recurring->update(['is_active' => true]);

        return back()->with('success', __('app.recurring_resumed'));
    }

    /** @return array<int, array{value: string, label: string}> */
    private function frequencyOptions(): array
    {
        return array_map(
            fn (RecurrenceFrequency $f) => ['value' => $f->value, 'label' => $f->label()],
            RecurrenceFrequency::cases(),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyDefaultExpenseAccount(array $validated, string $organizationId): array
    {
        if (filled($validated['expense_account_code'] ?? null)) {
            return $validated;
        }

        $category = ExpenseCategory::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('name', $validated['category'] ?? '')
            ->with(['defaultExpenseAccount' => function ($query) use ($organizationId): void {
                $query->where('organization_id', $organizationId)
                    ->where('is_active', true)
                    ->where('type', AccountType::Expense);
            }])
            ->first();

        if ($category?->defaultExpenseAccount?->code !== null) {
            $validated['expense_account_code'] = $category->defaultExpenseAccount->code;
        }

        return $validated;
    }
}
