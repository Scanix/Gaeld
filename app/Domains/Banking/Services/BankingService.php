<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use Illuminate\Support\Facades\DB;

class BankingService
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Record a bank transaction and post to ledger.
     *
     * Creates the BankTransaction record first, then delegates the
     * ledger posting to LedgerService::postBankTransaction().
     */
    public function recordTransaction(
        BankAccount $bankAccount,
        array $data,
        string $contraAccountCode,
    ): BankTransaction {
        return DB::transaction(function () use ($bankAccount, $data, $contraAccountCode) {
            $amount = abs((float) $data['amount']);
            $type = $data['type'] ?? BankTransaction::TYPE_CREDIT;

            $transaction = BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'date' => $data['date'],
                'description' => $data['description'] ?? null,
                'amount' => $amount,
                'type' => $type,
                'reference' => $data['reference'] ?? null,
            ]);

            return $this->ledgerService->postBankTransaction($transaction, $contraAccountCode);
        });
    }

    /**
     * Reconcile a bank transaction.
     */
    public function reconcile(BankTransaction $transaction): BankTransaction
    {
        $transaction->update(['is_reconciled' => true]);

        return $transaction;
    }
}
