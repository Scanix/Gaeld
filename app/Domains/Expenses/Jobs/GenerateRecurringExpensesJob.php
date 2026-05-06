<?php

namespace App\Domains\Expenses\Jobs;

use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Models\RecurringExpense;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRecurringExpensesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(CreateExpenseAction $createExpense): void
    {
        $dueRecurrings = RecurringExpense::withoutGlobalScope('organization')
            ->active()
            ->due(Carbon::today())
            ->get();

        foreach ($dueRecurrings as $recurring) {
            try {
                $this->generateExpense($recurring, $createExpense);
                $this->advanceSchedule($recurring);
            } catch (\DomainException|\RuntimeException|\InvalidArgumentException $e) {
                Log::error('GenerateRecurringExpensesJob: failed to generate', [
                    'recurring_expense_id' => $recurring->id,
                    'organization_id' => $recurring->organization_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function generateExpense(
        RecurringExpense $recurring,
        CreateExpenseAction $createExpense,
    ): void {
        $data = CreateExpenseData::fromArray([
            'organization_id' => $recurring->organization_id,
            'category' => $recurring->category,
            'description' => $recurring->description,
            'amount' => (string) $recurring->amount,
            'vat_amount' => (string) $recurring->vat_amount,
            'vat_rate_id' => $recurring->vat_rate_id ? (string) $recurring->vat_rate_id : null,
            'date' => $recurring->next_due_date->toDateString(),
            'vendor' => $recurring->vendor,
            'supplier_id' => $recurring->supplier_id ? (string) $recurring->supplier_id : null,
            'currency' => $recurring->currency,
            'payment_method' => $recurring->payment_method,
            'expense_account_code' => $recurring->expense_account_code,
            'bank_account_code' => $recurring->bank_account_code,
        ]);

        $createExpense->execute($data);

        Log::info('GenerateRecurringExpensesJob: expense created', [
            'recurring_expense_id' => $recurring->id,
            'organization_id' => $recurring->organization_id,
        ]);
    }

    private function advanceSchedule(RecurringExpense $recurring): void
    {
        $nextDate = $recurring->frequency->nextDate($recurring->next_due_date);

        if ($recurring->end_date && $nextDate->greaterThan($recurring->end_date)) {
            $recurring->update([
                'is_active' => false,
                'next_due_date' => $nextDate,
            ]);
        } else {
            $recurring->update([
                'next_due_date' => $nextDate,
            ]);
        }
    }
}
