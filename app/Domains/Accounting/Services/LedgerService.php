<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Exceptions\AlreadyPostedException;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Exceptions\InvalidEntryDataException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
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
 * Domain actions (FinalizeInvoiceAction, PostExpenseAction, BankingService)
 * call postEntry() for the actual ledger write.
 */
class LedgerService
{
    private const REFERENCE_PREFIX_REVERSAL = 'REV-';

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
     * @param  JournalEntryData  $entry  Header + balanced lines
     * @return JournalEntry The posted journal entry with lines eager-loaded
     *
     * @throws UnbalancedEntryException When SUM(debit) ≠ SUM(credit)
     * @throws InvalidEntryDataException When amounts are zero or accounts invalid
     * @throws DuplicateReferenceException When a posted entry with the same reference already exists
     */
    public function postEntry(string $organizationId, JournalEntryData $entry): JournalEntry
    {
        $this->validateBalance($entry->lines);
        $this->validateAccounts($organizationId, $entry->lines);
        $this->throwIfDuplicateReference($organizationId, $entry->reference);

        return $this->persistEntry($organizationId, $entry, true);
    }

    /**
     * Create a draft journal entry (not yet posted).
     *
     * Drafts still enforce balance validation but are not visible
     * in account balances or trial-balance reports until posted.
     */
    public function createDraft(string $organizationId, JournalEntryData $entry): JournalEntry
    {
        $this->validateBalance($entry->lines);
        $this->validateAccounts($organizationId, $entry->lines);

        return $this->persistEntry($organizationId, $entry, false);
    }

    /**
     * Post a previously draft journal entry.
     *
     * @throws AlreadyPostedException When the entry is already posted
     * @throws UnbalancedEntryException When the entry lines are unbalanced
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
     *
     * @throws DuplicateReferenceException if this entry has already been reversed
     */
    public function reverseEntry(JournalEntry $journalEntry, ?string $description = null): JournalEntry
    {
        $lines = $journalEntry->lines->map(fn (TransactionLine $line) => new JournalLineData(
            accountId: $line->account_id,
            debit: (string) $line->credit,
            credit: (string) $line->debit,
            description: 'Reversal: '.($line->description ?? ''),
        ))->all();

        return $this->postEntry($journalEntry->organization_id, new JournalEntryData(
            date: now()->toDateString(),
            reference: self::REFERENCE_PREFIX_REVERSAL.$journalEntry->reference,
            description: $description ?? 'Reversal of '.$journalEntry->reference,
            lines: $lines,
        ));
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
     * @param  int  $accountId  The account's primary key
     * @param  string|null  $fromDate  Start date (inclusive, Y-m-d)
     * @param  string|null  $toDate  End date (inclusive, Y-m-d)
     * @return string The calculated balance (bcmath-compatible string, 2 decimal places)
     */
    public function accountBalance(int $accountId, ?string $fromDate = null, ?string $toDate = null): string
    {
        $account = Account::findOrFail($accountId);
        $cacheKey = "account_balance:{$accountId}:{$fromDate}:{$toDate}";
        $orgTag = "org:{$account->organization_id}:ledger";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addHour(), function () use ($accountId, $account, $fromDate, $toDate) {
            $query = TransactionLine::where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
                    $q->where('is_posted', true)
                        ->when($fromDate, fn ($q, $date) => $q->where('date', '>=', $date))
                        ->when($toDate, fn ($q, $date) => $q->where('date', '<=', $date));
                });

            $debits = (string) (clone $query)->sum('debit');
            $credits = (string) (clone $query)->sum('credit');

            return $this->isDebitNormalAccount($account->type)
                ? bcsub($debits, $credits, 2)
                : bcsub($credits, $debits, 2);
        });
    }

    /**
     * Get the most recent posted journal entries for an organization.
     *
     * @return Collection<int, JournalEntry>
     */
    public function recentEntries(string $organizationId, int $limit = 10): Collection
    {
        return JournalEntry::where('organization_id', $organizationId)
            ->with('lines.account')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trial balance for an organization.
     *
     * Returns all accounts with non-zero posted balances, ordered by code.
     * Results cached per organization (tag: org:{orgId}:ledger).
     *
     * @param  string  $organizationId  UUID of the organization
     * @param  string|null  $asOfDate  Cut-off date (inclusive)
     * @return array<array{account_code: string, account_name: string, account_type: string, debit: string, credit: string}>
     */
    public function trialBalance(string $organizationId, ?string $asOfDate = null): array
    {
        $cacheKey = "trial_balance:{$organizationId}:{$asOfDate}";
        $orgTag = "org:{$organizationId}:ledger";

        return Cache::tags([$orgTag])->remember($cacheKey, now()->addMinutes(30), function () use ($organizationId, $asOfDate) {
            $rows = $this->buildTrialBalanceQuery($organizationId, $asOfDate)->get();

            return $this->computeTrialBalances($rows);
        });
    }

    private function buildTrialBalanceQuery(string $organizationId, ?string $asOfDate): Builder
    {
        return Account::where('accounts.organization_id', $organizationId)
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
    }

    private function computeTrialBalances(Collection $rows): array
    {
        $balances = [];

        foreach ($rows as $row) {
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

    // ──────────────────────────────────────────────────────────────
    //  Validation
    // ──────────────────────────────────────────────────────────────

    /**
     * Validate that all account IDs in the lines exist and belong to the organization.
     *
     * @param  JournalLineData[]  $lines
     *
     * @throws InvalidEntryDataException When an account is missing or belongs to another org
     */
    private function validateAccounts(string $organizationId, array $lines): void
    {
        $accountIds = array_unique(array_map(fn ($l) => $l->accountId, $lines));

        $existingCount = Account::where('organization_id', $organizationId)
            ->whereIn('id', $accountIds)
            ->count();

        if ($existingCount !== count($accountIds)) {
            throw new InvalidEntryDataException(
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
     * @throws ModelNotFoundException
     */
    public function resolveAccount(string $organizationId, string $code): Account
    {
        return Account::where('organization_id', $organizationId)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function persistEntry(string $organizationId, JournalEntryData $entry, bool $isPosted): JournalEntry
    {
        return DB::transaction(function () use ($organizationId, $entry, $isPosted) {
            $journalEntry = JournalEntry::create([
                'organization_id' => $organizationId,
                'date' => $entry->date,
                'reference' => $entry->reference,
                'description' => $entry->description,
                'is_posted' => $isPosted,
            ]);

            foreach ($entry->lines as $line) {
                TransactionLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line->accountId,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'description' => $line->description,
                ]);
            }

            // Flush cached balances so reports reflect the new entry immediately
            if ($isPosted) {
                $this->flushCache($organizationId);
            }

            return $journalEntry->load('lines.account');
        });
    }

    private function isDebitNormalAccount(AccountType|string $type): bool
    {
        if ($type instanceof AccountType) {
            return $type->isDebitNormal();
        }

        return AccountType::from($type)->isDebitNormal();
    }

    /**
     * Validate that the sum of debits equals the sum of credits.
     *
     * Uses bcmath for precision — no floating-point rounding errors.
     *
     * @throws UnbalancedEntryException When debits ≠ credits
     * @throws InvalidEntryDataException When all amounts are zero
     */
    private function validateBalance(array $lines): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, (string) $line->debit, 2);
            $totalCredit = bcadd($totalCredit, (string) $line->credit, 2);
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw new UnbalancedEntryException(
                "Entry is not balanced: debit={$totalDebit}, credit={$totalCredit}"
            );
        }

        if (bccomp($totalDebit, '0', 2) === 0) {
            throw new InvalidEntryDataException('Journal entry must have non-zero amounts.');
        }
    }

    /**
     * Guard against posting duplicate references within the same organization.
     *
     * Null references are always allowed (e.g. bank imports without ref).
     *
     * @throws DuplicateReferenceException When a posted entry with the same reference exists
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
