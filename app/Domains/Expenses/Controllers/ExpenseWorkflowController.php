<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Expense approval and ledger posting workflow.
 */
class ExpenseWorkflowController extends Controller
{
    public function approve(Expense $expense, ApproveExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        try {
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_approved'));
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
        } catch (ModelNotFoundException) {
            return redirect()->back()->with('error', __('app.account_not_found', ['code' => $validated['expense_account_code']]));
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_posted'));
    }
}
