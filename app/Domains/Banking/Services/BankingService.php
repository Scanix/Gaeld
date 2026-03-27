<?php

namespace App\Domains\Banking\Services;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Banking\DTOs\RecordBankTransactionData;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class BankingService
{
    private const REFERENCE_PREFIX_BANK = 'BNK-';

    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Record a bank transaction and post to ledger.
     *
     * Creates the BankTransaction record first, then posts to the ledger.
     */
    public function recordTransaction(
        BankAccount $bankAccount,
        RecordBankTransactionData $data,
    ): BankTransaction {
        return DB::transaction(function () use ($bankAccount, $data) {
            $amount = Money::absoluteAmount($data->amount);

            $transaction = BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'date' => $data->date,
                'description' => $data->description,
                'amount' => $amount,
                'type' => $data->type,
                'reference' => $data->reference,
            ]);

            return $this->postBankTransaction($transaction, $data->contraAccountCode);
        });
    }

    /**
     * Post a bank transaction to the ledger.
     *
     * Accounting effect for deposits (credit type):
     *   Debit  Bank account (1020)    (amount)
     *   Credit Contra account          (amount)
     *
     * Accounting effect for withdrawals (debit type):
     *   Debit  Contra account          (amount)
     *   Credit Bank account (1020)     (amount)
     *
     * @throws UnlinkedBankAccountException When bank account has no linked ledger account
     */
    public function postBankTransaction(BankTransaction $transaction, string $contraAccountCode): BankTransaction
    {
        return DB::transaction(function () use ($transaction, $contraAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            $bankLedgerAccount = $bankAccount->ledgerAccount;
            if (! $bankLedgerAccount) {
                throw new UnlinkedBankAccountException;
            }

            $contraAccount = $this->ledgerService->resolveAccount($orgId, $contraAccountCode);
            $amount = Money::absoluteAmount((string) $transaction->amount);
            $isDeposit = $transaction->type === BankTransactionType::Credit;

            $lines = $this->buildBankTransactionLines($bankLedgerAccount, $contraAccount, $amount, $isDeposit, $transaction->description);

            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $transaction->date->toDateString(),
                reference: $transaction->reference ?? self::REFERENCE_PREFIX_BANK.$transaction->id,
                description: $transaction->description,
                lines: $lines,
            ));

            $transaction->update(['journal_entry_id' => $journalEntry->id]);

            $this->updateBankAccountBalance($bankAccount, $amount, $isDeposit);

            return $transaction->fresh(['journalEntry.lines', 'bankAccount']);
        });
    }

    /**
     * Update a bank account's denormalized balance field.
     */
    public function updateBankAccountBalance(BankAccount $bankAccount, string $amount, bool $isDeposit): void
    {
        $newBalance = $isDeposit
            ? bcadd((string) $bankAccount->balance, $amount, 2)
            : bcsub((string) $bankAccount->balance, $amount, 2);

        $bankAccount->update(['balance' => $newBalance]);
    }

    /**
     * @return JournalLineData[]
     */
    private function buildBankTransactionLines(
        Account $bankLedgerAccount,
        Account $contraAccount,
        string $amount,
        bool $isDeposit,
        ?string $description,
    ): array {
        return $isDeposit
            ? [
                new JournalLineData(accountId: $bankLedgerAccount->id, debit: $amount, credit: '0', description: 'Bank deposit'),
                new JournalLineData(accountId: $contraAccount->id, debit: '0', credit: $amount, description: $description ?? ''),
            ]
            : [
                new JournalLineData(accountId: $contraAccount->id, debit: $amount, credit: '0', description: $description ?? ''),
                new JournalLineData(accountId: $bankLedgerAccount->id, debit: '0', credit: $amount, description: 'Bank withdrawal'),
            ];
    }
}
