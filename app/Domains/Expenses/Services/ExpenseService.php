<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\DTOs\RecordExpensePaymentData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Post the ledger entry for an expense paid via bank reconciliation.
     *
     * Accounting effect:
     *   Debit  {expenseAccountCode} Expense account  (payment amount)
     *   Credit {bankAccountCode}    Bank account     (payment amount)
     *
     * Marks the expense as Posted.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException  When account code not found
     */
    public function recordBankPayment(Expense $expense, RecordExpensePaymentData $data): JournalEntry
    {
        return DB::transaction(function () use ($expense, $data) {
            $orgId = $expense->organization_id;
            $bankAccountCode = $data->bankAccountCode ?? AccountCode::BANK_CASH;

            $expenseAccount = $this->ledgerService->resolveAccount($orgId, $data->expenseAccountCode);
            $bankAccount = $this->ledgerService->resolveAccount($orgId, $bankAccountCode);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $data->paymentDate,
                reference: $data->reference,
                description: $data->description,
                lines: [
                    new JournalLineData(accountId: $expenseAccount->id, debit: $data->amount, credit: 0, description: $expense->description ?? 'Expense'),
                    new JournalLineData(accountId: $bankAccount->id, debit: 0, credit: $data->amount, description: 'Bank withdrawal'),
                ],
            ));

            $expense->update(['status' => ExpenseStatus::Posted->value]);

            return $journalEntry;
        });
    }
}
