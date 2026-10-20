<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\ReconciliationFailedException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;

trait ReconciliationPreconditions
{
    private function validateDocumentCurrency(BankAccount $bankAccount, string $documentCurrency): void
    {
        $ledgerCurrency = strtoupper($bankAccount->organization->currency ?: 'CHF');

        if (strtoupper($documentCurrency) !== $ledgerCurrency
            || strtoupper($bankAccount->currency) !== $ledgerCurrency) {
            throw new ReconciliationFailedException(
                'Automatic reconciliation requires the document, bank account, and organization to use the same currency. Record foreign-currency settlements separately with their exchange rate.',
            );
        }
    }

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
