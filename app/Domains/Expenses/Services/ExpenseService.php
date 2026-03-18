<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Accounting\AccountCode;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Expenses\Models\Expense;

class ExpenseService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Post an expense into the ledger.
     *
     * Delegates to LedgerService::postExpense() which handles:
     *   Debit  {expenseAccountCode} Expense account
     *   Credit {bankAccountCode}    Bank / Cash
     */
    public function postExpense(Expense $expense, string $expenseAccountCode, string $bankAccountCode = AccountCode::BANK_CASH): Expense
    {
        return $this->ledgerService->postExpense($expense, $expenseAccountCode, $bankAccountCode);
    }
}
