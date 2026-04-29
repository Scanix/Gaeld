<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Exceptions\ExpenseLedgerPostingException;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Notifications\ExpenseApprovedNotification;
use App\Domains\Reporting\Services\DashboardService;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;

/**
 * Expense approval and ledger posting workflow.
 */
class ExpenseWorkflowController extends Controller
{
    use HandlesFlashErrorResponses;

    public function approve(Expense $expense, ApproveExpenseAction $action, DashboardService $dashboardService): RedirectResponse
    {
        $this->authorize('update', $expense);

        try {
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return $this->backWithError($e);
        }

        $dashboardService->flushCache($expense->organization_id);

        // Notify the submitter if they're tracked on the expense
        $submitter = $expense->user;
        if ($submitter) {
            $submitter->notify(new ExpenseApprovedNotification($expense));
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_approved'));
    }

    public function postToLedger(Expense $expense, PostExpenseAction $action, DashboardService $dashboardService): RedirectResponse
    {
        $this->authorize('update', $expense);

        if (! $expense->expense_account_code) {
            return $this->backWithError(__('app.expense_account_code_required'));
        }

        try {
            $action->execute(
                $expense,
                $expense->expense_account_code,
                $expense->bank_account_code ?? AccountCode::BANK_CASH,
            );
        } catch (InvalidExpenseStateException|ExpenseLedgerPostingException $e) {
            return $this->backWithError($e);
        } catch (ModelNotFoundException) {
            return $this->backWithError(__('app.account_not_found', ['code' => $expense->expense_account_code]));
        }

        $dashboardService->flushCache($expense->organization_id);

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_posted'));
    }
}
