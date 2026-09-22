<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\VatPeriodLockService;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a posted expense and posts a dated contra-entry for its journal entry.
 */
class CancelExpenseAction
{
    public function __construct(
        private LedgerService $ledgerService,
        private VatPeriodLockService $vatPeriodLocks,
    ) {}

    public function execute(Expense $expense): Expense
    {
        if (! $expense->status->canTransitionTo(ExpenseStatus::Cancelled)) {
            throw new InvalidExpenseStateException("Cannot cancel an expense with status: {$expense->status->value}.");
        }

        if (! $expense->journal_entry_id) {
            throw new InvalidExpenseStateException('Cannot cancel an expense without a journal entry.');
        }

        $this->vatPeriodLocks->assertPeriodUnlocked(
            $expense->organization_id,
            $expense->date->toDateString(),
            $expense->date->toDateString(),
        );

        $expense->loadMissing('journalEntry.lines');

        if ($expense->journalEntry === null) {
            throw new InvalidExpenseStateException('Cannot cancel an expense whose journal entry is missing.');
        }

        return DB::transaction(function () use ($expense): Expense {
            $reversal = $this->ledgerService->reverseEntry(
                $expense->journalEntry,
                "Cancellation of expense {$expense->id}",
                'expense_cancellation',
                $expense->date->toDateString(),
            );
            $this->ledgerService->postDraft($reversal);

            $expense->update(['status' => ExpenseStatus::Cancelled]);

            return $expense->fresh(['journalEntry.lines']);
        });
    }
}
