<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Exceptions\ExpenseLedgerPostingException;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;

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

    public function postToLedger(Expense $expense, PostExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        if (! $expense->expense_account_code) {
            return redirect()->back()->with('error', __('app.expense_account_code_required'));
        }

        try {
            $action->execute(
                $expense,
                $expense->expense_account_code,
                $expense->bank_account_code ?? AccountCode::BANK_CASH,
            );
        } catch (InvalidExpenseStateException|ExpenseLedgerPostingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (ModelNotFoundException) {
            return redirect()->back()->with('error', __('app.account_not_found', ['code' => $expense->expense_account_code]));
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_posted'));
    }
}
