<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;

trait ReconciliationPreconditions
{
    private function validatePreconditions(BankTransaction $transaction, BankAccount $bankAccount): void
    {
        if (! $bankAccount->ledgerAccount) {
            throw new UnlinkedBankAccountException;
        }

        if ($transaction->is_reconciled) {
            throw new AlreadyReconciledException;
        }
    }
}
