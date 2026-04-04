<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles bank transactions against contra accounts and handles
 * personal transaction marking for mixed-use accounts.
 */
class ContraAccountReconciler
{
    use ReconciliationPreconditions;

    public function __construct(
        private BankingService $bankingService,
        private PersonalPatternService $personalPatternService,
    ) {}

    /**
     * Manually reconcile a bank transaction with a contra account.
     *
     * @throws AlreadyReconciledException
     * @throws UnlinkedBankAccountException
     */
    public function reconcileWithContraAccount(
        BankTransaction $transaction,
        string $contraAccountCode,
    ): BankTransaction {
        return DB::transaction(function () use ($transaction, $contraAccountCode) {
            $bankAccount = $transaction->bankAccount;

            $this->validatePreconditions($transaction, $bankAccount);

            $postedTransaction = $this->bankingService->postBankTransaction($transaction, $contraAccountCode);
            $postedTransaction->update(['is_reconciled' => true]);

            return $postedTransaction->fresh(['journalEntry.lines', 'bankAccount']);
        });
    }

    /**
     * Mark a bank transaction as personal on a mixed-use account.
     *
     * @throws AlreadyReconciledException
     * @throws UnlinkedBankAccountException
     */
    public function reconcileAsPersonal(BankTransaction $transaction): BankTransaction
    {
        return DB::transaction(function () use ($transaction) {
            $bankAccount = $transaction->bankAccount;

            $this->validatePreconditions($transaction, $bankAccount);

            $postedTransaction = $this->bankingService->postBankTransaction(
                $transaction,
                AccountCode::PRIVATE_WITHDRAWALS,
            );

            $postedTransaction->update([
                'is_reconciled' => true,
                'is_personal' => true,
            ]);

            $this->personalPatternService->recordPersonalTransaction(
                $transaction,
                $bankAccount->organization_id,
            );

            return $postedTransaction->fresh(['journalEntry.lines', 'bankAccount']);
        });
    }

    /**
     * Bulk mark transactions as personal.
     *
     * @param  Collection<int, BankTransaction>  $transactions
     * @return array{reconciled: int, skipped: int}
     */
    public function bulkReconcileAsPersonal(Collection $transactions): array
    {
        $reconciled = 0;
        $skipped = 0;

        foreach ($transactions as $transaction) {
            try {
                $this->reconcileAsPersonal($transaction);
                $reconciled++;
            } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
                Log::info('Bulk personal reconcile: skipped', [
                    'transaction_id' => $transaction->id,
                    'reason' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        return ['reconciled' => $reconciled, 'skipped' => $skipped];
    }
}
