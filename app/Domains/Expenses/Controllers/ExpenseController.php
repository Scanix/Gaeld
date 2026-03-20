<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Actions\UpdateExpenseAction;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        return Inertia::render('Expenses/Index', [
            'expenses' => ExpenseQuery::list($request),
            'query' => [
                'sort' => $request->input('sort', 'date'),
                'direction' => $request->input('direction', 'desc'),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('Expenses/Create', [
            'vatRates' => VatRate::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request, CreateExpenseAction $action): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', app('current_organization')->id),
            ],
            'date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $this->storeReceipt(
                $request->file('receipt'),
                app('current_organization')->id,
            );
        }
        $validated['organization_id'] = app('current_organization')->id;

        $expense = $action->execute(CreateExpenseData::fromArray($validated));

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense created.');
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense->load(['vatRate', 'journalEntry.lines.account']),
            'receiptUrl' => $expense->receipt_path ? Storage::url($expense->receipt_path) : null,
        ]);
    }

    public function edit(Request $request, Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense->load('vatRate'),
            'vatRates' => VatRate::where('is_active', true)->get(),
            'receiptUrl' => $expense->receipt_path ? Storage::url($expense->receipt_path) : null,
        ]);
    }

    public function update(Request $request, Expense $expense, UpdateExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $expense->organization_id),
            ],
            'date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($request->hasFile('receipt')) {
            $this->deleteReceiptIfExists($expense->receipt_path);
            $validated['receipt_path'] = $this->storeReceipt(
                $request->file('receipt'),
                $expense->organization_id,
            );
        }

        try {
            $action->execute($expense, UpdateExpenseData::fromArray($validated));
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense, DeleteExpenseAction $action): RedirectResponse
    {
        $this->authorize('delete', $expense);

        try {
            $this->deleteReceiptIfExists($expense->receipt_path);
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted.');
    }

    public function approve(Expense $expense, ApproveExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        try {
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense approved.');
    }

    public function postToLedger(Expense $expense, Request $request, PostExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'expense_account_code' => 'required|string',
        ]);

        try {
            $action->execute($expense, $validated['expense_account_code']);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense posted to ledger.');
    }

    public function removeReceipt(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if ($expense->receipt_path) {
            $this->deleteReceiptIfExists($expense->receipt_path);
            $expense->update(['receipt_path' => null]);
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Receipt removed.');
    }

    private function storeReceipt(\Illuminate\Http\UploadedFile $file, string $orgId): string
    {
        return $file->store("receipts/{$orgId}", 'local');
    }

    private function deleteReceiptIfExists(?string $path): void
    {
        if ($path) {
            Storage::delete($path);
        }
    }
}
