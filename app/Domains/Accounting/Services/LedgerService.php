<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\ValueObjects\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Exceptions\AlreadyPostedException;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Central ledger service for the Gäld accounting engine.
 *
 * Every financial mutation flows through this service. It enforces:
 * - Double-entry: SUM(debit) = SUM(credit) on every journal entry
 * - Atomic persistence via database transactions
 * - Account existence validation within the organization
 * - Duplicate reference prevention
 *
 * Domain services (InvoiceService, ExpenseService, BankingService) delegate
 * here for the actual ledger write. Convenience methods postInvoice(),
 * postExpense(), and postBankTransaction() provide direct one-call posting
 * for use in seeders, CLI commands, or plugin code.
 */
class LedgerService
{
    private const REFERENCE_PREFIX_REVERSAL = 'REV-';
    private const REFERENCE_PREFIX_BANK = 'BNK-';
    private const REFERENCE_PREFIX_EXPENSE = 'EXP-';

    // ──────────────────────────────────────────────────────────────
    //  Core Posting
    // ──────────────────────────────────────────────────────────────

    /**
     * Post a journal entry with balanced debit/credit lines.
     *
     * This is the primary entry point for all ledger writes. Every debit
     * must have a matching credit — the method validates this before
     * persisting. The resulting JournalEntry is immediately marked as posted.
     *
     * @param  string  $organizationId  UUID of the owning organization
     * @param  array{date: string, reference?: string, description?: string}  $entryData
     * @param  array<array{account_id: string, debit: string, credit: string, description?: string}>  $lines
     * @return JournalEntry  The posted journal entry with lines eager-loaded
     *
     * @throws UnbalancedEntryException  When SUM(debit) ≠ SUM(credit)
     * @throws \InvalidArgumentException  When amounts are zero or accounts invalid
     */
    public function postEntry(string $organizationId, array $entryData, array $lines): JournalEntry
    {
        $this->validateBalance($lines);
        $this->validateAccounts($organizationId, $lines);
        $this->throwIfDuplicateReference($organizationId, $entryData['reference'] ?? null);

        return $this->persistEntry($organizationId, $entryData, $lines, true);
    }

    /**
     * Create a draft journal entry (not yet posted).
     *
     * Drafts still enforce balance validation but are not visible
     * in account balances or trial-balance reports until posted.
     */
    public function createDraft(string $organizationId, array $entryData, array $lines): JournalEntry
    {
        $this->validateBalance($lines);
        $this->validateAccounts($organizationId, $lines);

        return $this->persistEntry($organizationId, $entryData, $lines, false);
    }

    /**
     * Post a previously draft journal entry.
     *
     * @throws AlreadyPostedException  When the entry is already posted
     * @throws UnbalancedEntryException  When the entry lines are unbalanced
     */
    public function postDraft(JournalEntry $journalEntry): JournalEntry
    {
        if ($journalEntry->is_posted) {
            throw new AlreadyPostedException('Journal entry is already posted.');
        }

        if (! $journalEntry->isBalanced()) {
            throw new UnbalancedEntryException('Journal entry is not balanced.');
        }

        $journalEntry->update(['is_posted' => true]);

        return $journalEntry;
    }

    /**
     * Reverse a posted journal entry by creating a contra entry.
     *
     * Swaps debit ↔ credit on every line and posts a new entry
     * with a REV- reference prefix.
     */
    public function reverseEntry(JournalEntry $journalEntry, ?string $description = null): JournalEntry
    {
        $lines = $journalEntry->lines->map(fn (TransactionLine $line) => [
            'account_id' => $line->account_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'description' => 'Reversal: ' . ($line->description ?? ''),
        ])->toArray();

        return $this->postEntry($journalEntry->organization_id, [
            'date' => now()->toDateString(),
            'reference' => self::REFERENCE_PREFIX_REVERSAL . $journalEntry->reference,
            'description' => $description ?? 'Reversal of ' . $journalEntry->reference,
        ], $lines);
    }

    // ──────────────────────────────────────────────────────────────
    //  Domain Convenience Methods
    // ──────────────────────────────────────────────────────────────

    /**
     * Finalize and post an invoice to the ledger.
     *
     * Accounting effect:
     *   Debit  1100 Accounts Receivable  (invoice total)
     *   Credit 3000 Revenue from Services (invoice total)
     *
     * The invoice status is moved from draft → sent and the resulting
     * journal entry is linked via invoice.journal_entry_id.
     *
     * @param  Invoice  $invoice  Must be in STATUS_DRAFT
     * @return Invoice  The updated invoice with journalEntry loaded
     *
     * @throws InvalidInvoiceStateException  When invoice is not a draft
     */
    public function postInvoice(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new InvalidInvoiceStateException('Only draft invoices can be posted.');
        }

        return DB::transaction(function () use ($invoice) {
            $orgId = $invoice->organization_id;

            $ar = $this->resolveAccount($orgId, AccountCode::ACCOUNTS_RECEIVABLE);
            $revenue = $this->resolveAccount($orgId, AccountCode::REVENUE);

            $journalEntry = $this->postEntry($orgId, [
                'date' => $invoice->issue_date->toDateString(),
                'reference' => $invoice->number,
                'description' => "Invoice {$invoice->number} — " . ($invoice->customer?->name ?? 'N/A'),
            ], [
                ['account_id' => $ar->id, 'debit' => $invoice->total, 'credit' => 0, 'description' => 'Accounts Receivable'],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $invoice->total, 'description' => 'Revenue'],
            ]);

            $invoice->update([
                'status' => InvoiceStatus::Sent->value,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $invoice->fresh(['lines', 'customer', 'journalEntry.lines']);
        });
    }

    /**
     * Post an expense to the ledger.
     *
     * Accounting effect:
     *   Debit  {expenseAccountCode} Expense account  (expense amount)
     *   Credit {bankAccountCode}    Bank / Cash       (expense amount)
     *
     * @param  Expense  $expense  Must not already be posted
     * @param  string   $expenseAccountCode  Chart-of-accounts code for the expense category (e.g. '6530')
     * @param  string   $bankAccountCode     Payment source account code (default '1020')
     * @return Expense  The updated expense with journalEntry loaded
     *
     * @throws InvalidExpenseStateException  When expense is already posted
     */
    public function postExpense(Expense $expense, string $expenseAccountCode, string $bankAccountCode = AccountCode::BANK_CASH): Expense
    {
        if ($expense->status === ExpenseStatus::Posted) {
            throw new \App\Domains\Expenses\Exceptions\InvalidExpenseStateException('Expense is already posted.');
        }

        return DB::transaction(function () use ($expense, $expenseAccountCode, $bankAccountCode) {
            $orgId = $expense->organization_id;

            $expenseAccount = $this->resolveAccount($orgId, $expenseAccountCode);
            $bankAccount = $this->resolveAccount($orgId, $bankAccountCode);

            $journalEntry = $this->postEntry($orgId, [
                'date' => $expense->date->toDateString(),
                'reference' => self::REFERENCE_PREFIX_EXPENSE . $expense->id,
                'description' => $expense->description ?? $expense->category,
            ], [
                ['account_id' => $expenseAccount->id, 'debit' => $expense->amount, 'credit' => 0, 'description' => $expense->description],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => $expense->amount, 'description' => 'Payment from bank'],
            ]);

            $expense->update([
                'status' => ExpenseStatus::Posted->value,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $expense->fresh(['journalEntry.lines']);
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
     * @param  BankTransaction  $transaction       The bank transaction (must have bankAccount loaded)
     * @param  string           $contraAccountCode  The opposing account code (e.g. '3000' for revenue, '6530' for software expense)
     * @return BankTransaction  The updated transaction with journalEntry loaded
     *
     * @throws UnlinkedBankAccountException  When bank account has no linked ledger account
     */
    public function postBankTransaction(BankTransaction $transaction, string $contraAccountCode): BankTransaction
    {
        return DB::transaction(function () use ($transaction, $contraAccountCode) {
            $bankAccount = $transaction->bankAccount;
            $orgId = $bankAccount->organization_id;

            $bankLedgerAccount = $bankAccount->ledgerAccount;
            if (! $bankLedgerAccount) {
                throw new UnlinkedBankAccountException();
            }

            $contraAccount = $this->resolveAccount($orgId, $contraAccountCode);
            $amount = Money::absoluteAmount((string) $transaction->amount);
            $isDeposit = $transaction->type === BankTransaction::TYPE_CREDIT;

            $lines = $this->buildBankTransactionLines($bankLedgerAccount, $contraAccount, $amount, $isDeposit, $transaction->description);

            $journalEntry = $this->postEntry($orgId, [
                'date' => $transaction->date->toDateString(),
                'reference' => $transaction->reference ?? self::REFERENCE_PREFIX_BANK . $transaction->id,
                'description' => $transaction->description,
            ], $lines);

            $transaction->update(['journal_entry_id' => $journalEntry->id]);

            $this->updateBankAccountBalance($bankAccount, $amount, $isDeposit);

            return $transaction->fresh(['journalEntry.lines', 'bankAccount']);
        });
    }

    /**
     * Build the debit/credit line pair for a bank transaction journal entry.
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
                ['account_id' => $bankLedgerAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Bank deposit'],
                ['account_id' => $contraAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => $description ?? ''],
            ]
            : [
                ['account_id' => $contraAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => $description ?? ''],
                ['account_id' => $bankLedgerAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Bank withdrawal'],
            ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Reporting
    // ──────────────────────────────────────────────────────────────

    /**
     * Get the balance for an account within a date range.
     *
     * Asset and expense accounts return debit-normal balances (debits − credits).
     * Liability, equity, and revenue accounts return credit-normal (credits − debits).
     * Only posted entries are included.
     *
     * Results are cached per account + date range (tag: org:{orgId}:ledger).
     *
     * @param  int          $accountId  The account's primary key
     * @param  string|null  $fromDate   Start date (inclusive, Y-m-d)
     * @param  string|null  $toDate     End date (inclusive, Y-m-d)
     * @return string  The calculated balance (bcmath-compatible string, 2 decimal places)
     */
    public function accountBalance(int $accountId, ?string $fromDate = null, ?string $toDate = null): string
    {
        $account = Account::findOrFail($accountId);
        $cacheKey = "account_balance:{$accountId}:{$fromDate}:{$toDate}";
        $orgTag = "org:{$account->organization_id}:ledger";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addHour(), function () use ($accountId, $account, $fromDate, $toDate) {
            $query = TransactionLine::where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                    $q->where('is_posted', true);
                    if ($fromDate) {
                        $q->where('date', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->where('date', '<=', $toDate);
                    }
                });

            $debits = (string) (clone $query)->sum('debit');
            $credits = (string) (clone $query)->sum('credit');

            return $this->isDebitNormalAccount($account->type)
                ? bcsub($debits, $credits, 2)
                : bcsub($credits, $debits, 2);
        });
    }

    /**
     * Get trial balance for an organization.
     *
     * Returns all accounts with non-zero posted balances, ordered by code.
     * Results cached per organization (tag: org:{orgId}:ledger).
     *
     * @param  string       $organizationId  UUID of the organization
     * @param  string|null  $asOfDate        Cut-off date (inclusive)
     * @return array<array{account_code: string, account_name: string, account_type: string, debit: string, credit: string}>
     */
    public function trialBalance(string $organizationId, ?string $asOfDate = null): array
    {
        $cacheKey = "trial_balance:{$organizationId}:{$asOfDate}";
        $orgTag = "org:{$organizationId}:ledger";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $asOfDate) {
            $query = Account::where('accounts.organization_id', $organizationId)
                ->where('accounts.is_active', true)
                ->leftJoin('transaction_lines', 'transaction_lines.account_id', '=', 'accounts.id')
                ->leftJoin('journal_entries', function ($join) use ($asOfDate) {
                    $join->on('journal_entries.id', '=', 'transaction_lines.journal_entry_id')
                        ->where('journal_entries.is_posted', true);
                    if ($asOfDate) {
                        $join->where('journal_entries.date', '<=', $asOfDate);
                    }
                })
                ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
                ->orderBy('accounts.code')
                ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.type, COALESCE(SUM(transaction_lines.debit), 0) as total_debit, COALESCE(SUM(transaction_lines.credit), 0) as total_credit');

            $balances = [];

            foreach ($query->get() as $row) {
                $isDebitNormal = $this->isDebitNormalAccount($row->type);
                $balance = $isDebitNormal
                    ? bcsub((string) $row->total_debit, (string) $row->total_credit, 2)
                    : bcsub((string) $row->total_credit, (string) $row->total_debit, 2);

                if (bccomp($balance, '0', 2) !== 0) {
                    $balances[] = [
                        'account_code' => $row->code,
                        'account_name' => $row->name,
                        'account_type' => $row->type,
                        'debit' => $isDebitNormal && bccomp($balance, '0', 2) > 0 ? $balance : '0',
                        'credit' => ! $isDebitNormal && bccomp($balance, '0', 2) > 0 ? $balance : '0',
                    ];
                }
            }

            return $balances;
        });
    }

    /**
     * Flush all cached ledger data for an organization.
     *
     * Must be called after any journal entry mutation to maintain consistency.
     */
    public function flushCache(string $organizationId): void
    {
        Cache::tags(["org:{$organizationId}:ledger"])->flush();
        Cache::tags(["org:{$organizationId}:reports"])->flush();
    }

    /**
     * Update a bank account's denormalized balance field.
     *
     * This is the single authoritative path for balance mutations.
     * Deposits add to balance; withdrawals subtract.
     */
    public function updateBankAccountBalance(\App\Domains\Banking\Models\BankAccount $bankAccount, string $amount, bool $isDeposit): void
    {
        $newBalance = $isDeposit
            ? bcadd((string) $bankAccount->balance, $amount, 2)
            : bcsub((string) $bankAccount->balance, $amount, 2);

        $bankAccount->update(['balance' => $newBalance]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Validation
    // ──────────────────────────────────────────────────────────────

    /**
     * Validate that all account IDs in the lines exist and belong to the organization.
     *
     * @param  string  $organizationId
     * @param  array<array{account_id: string}>  $lines
     *
     * @throws \InvalidArgumentException  When an account is missing or belongs to another org
     */
    public function validateAccounts(string $organizationId, array $lines): void
    {
        $accountIds = array_unique(array_column($lines, 'account_id'));

        $existingCount = Account::where('organization_id', $organizationId)
            ->whereIn('id', $accountIds)
            ->count();

        if ($existingCount !== count($accountIds)) {
            throw new \InvalidArgumentException(
                'One or more accounts do not exist or do not belong to this organization.'
            );
        }
    }

    /**
     * Check whether a reference has already been used for a posted entry in this organization.
     */
    public function isDuplicateReference(string $organizationId, string $reference): bool
    {
        return JournalEntry::where('organization_id', $organizationId)
            ->where('reference', $reference)
            ->where('is_posted', true)
            ->exists();
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Resolve an account by its chart-of-accounts code within an organization.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resolveAccount(string $organizationId, string $code): Account
    {
        return Account::where('organization_id', $organizationId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function persistEntry(string $organizationId, array $entryData, array $lines, bool $isPosted): JournalEntry
    {
        return DB::transaction(function () use ($organizationId, $entryData, $lines, $isPosted) {
            $journalEntry = JournalEntry::create([
                'organization_id' => $organizationId,
                'date' => $entryData['date'],
                'reference' => $entryData['reference'] ?? null,
                'description' => $entryData['description'] ?? null,
                'is_posted' => $isPosted,
            ]);

            foreach ($lines as $line) {
                TransactionLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            // Flush cached balances so reports reflect the new entry immediately
            if ($isPosted) {
                $this->flushCache($organizationId);
            }

            return $journalEntry->load('lines.account');
        });
    }

    private function isDebitNormalAccount(string $type): bool
    {
        return AccountType::from($type)->isDebitNormal();
    }

    /**
     * Validate that the sum of debits equals the sum of credits.
     *
     * Uses bcmath for precision — no floating-point rounding errors.
     *
     * @throws UnbalancedEntryException  When debits ≠ credits
     * @throws \InvalidArgumentException  When all amounts are zero
     */
    private function validateBalance(array $lines): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, (string) ($line['debit'] ?? 0), 2);
            $totalCredit = bcadd($totalCredit, (string) ($line['credit'] ?? 0), 2);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw new UnbalancedEntryException(
                "Entry is not balanced: debit={$totalDebit}, credit={$totalCredit}"
            );
        }

        if (bccomp($totalDebit, '0', 2) === 0) {
            throw new \InvalidArgumentException('Journal entry must have non-zero amounts.');
        }
    }

    /**
     * Guard against posting duplicate references within the same organization.
     *
     * Null references are always allowed (e.g. bank imports without ref).
     *
     * @throws AlreadyPostedException  When a posted entry with the same reference exists
     */
    private function throwIfDuplicateReference(string $organizationId, ?string $reference): void
    {
        if ($reference === null) {
            return;
        }

        if ($this->isDuplicateReference($organizationId, $reference)) {
            throw new DuplicateReferenceException(
                "A posted journal entry with reference '{$reference}' already exists in this organization."
            );
        }
    }
}
