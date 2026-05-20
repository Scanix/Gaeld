<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Events\JournalDraftCreated;
use App\Domains\Accounting\Events\JournalDraftPosted;
use App\Domains\Accounting\Events\JournalEntryPosted;
use App\Domains\Accounting\Events\JournalEntryReversed;
use App\Domains\Accounting\Exceptions\AlreadyPostedException;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Exceptions\FiscalYearClosedException;
use App\Domains\Accounting\Exceptions\InvalidEntryDataException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Organizations\Models\Organization;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Core double-entry accounting service (write operations).
 *
 * Creates, posts, and reverses journal entries while enforcing balanced
 * debits/credits, unique references, and organization scoping.
 *
 * Read operations (balances, trial balance, account lookups) are in
 * LedgerQueryService.
 */
class LedgerService
{
    private const REFERENCE_PREFIX_REVERSAL = 'REV-';

    public function __construct(
        private LedgerQueryService $queryService,
    ) {}

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
     * @throws FiscalYearClosedException When the entry date falls in a closed fiscal year
     */
    public function postEntry(string $organizationId, JournalEntryData $entry): JournalEntry
    {
        $this->guardClosedFiscalYear($organizationId, $entry->date);
        $this->validateBalance($entry->lines);
        $this->validateAccounts($organizationId, $entry->lines);
        $this->throwIfDuplicateReference($organizationId, $entry->reference);

        $journalEntry = $this->persistEntry($organizationId, $entry, true);

        JournalEntryPosted::dispatch($journalEntry);

        return $journalEntry;
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

        $journalEntry = $this->persistEntry($organizationId, $entry, false);

        JournalDraftCreated::dispatch($journalEntry);

        return $journalEntry;
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

        $this->flushCache($journalEntry->organization_id);

        JournalDraftPosted::dispatch($journalEntry);

        return $journalEntry;
    }

    /**
     * Reverse a posted journal entry by creating a contra entry draft.
     *
     * Swaps debit ↔ credit on every line and creates a DRAFT entry
     * with a REV- reference prefix. User must explicitly post the draft
     * to finalize the reversal.
     *
     * @throws DuplicateReferenceException if this entry has already been reversed
     */
    public function reverseEntry(JournalEntry $journalEntry, ?string $description = null): JournalEntry
    {
        $reversalReference = self::REFERENCE_PREFIX_REVERSAL.$journalEntry->reference;

        // Prevent duplicate reversals - check if ANY entry (draft or posted) with this reference exists
        $existingReversal = JournalEntry::where('organization_id', $journalEntry->organization_id)
            ->where('reference', $reversalReference)
            ->exists();

        if ($existingReversal) {
            throw new DuplicateReferenceException(
                "This journal entry has already been reversed (reference '{$reversalReference}' exists)."
            );
        }

        $lines = $journalEntry->lines->map(fn (TransactionLine $line) => new JournalLineData(
            accountId: (string) $line->account_id,
            debit: (string) $line->credit,
            credit: (string) $line->debit,
            description: 'Reversal: '.($line->description ?? ''),
        ))->all();

        $reversalEntry = $this->createDraft($journalEntry->organization_id, new JournalEntryData(
            date: now()->toDateString(),
            reference: $reversalReference,
            description: $description ?? 'Reversal of '.$journalEntry->reference,
            lines: $lines,
        ));

        JournalEntryReversed::dispatch($reversalEntry, $journalEntry);

        return $reversalEntry;
    }

    // ──────────────────────────────────────────────────────────────
    //  Cache Management
    // ──────────────────────────────────────────────────────────────

    /**
     * Flush all cached ledger data for an organization.
     *
     * Must be called after any journal entry mutation to maintain consistency.
     */
    public function flushCache(string $organizationId): void
    {
        Cache::tags(["org:{$organizationId}:ledger"])->flush();
        Cache::tags(["org:{$organizationId}:reports"])->flush();
        Cache::tags(["org:{$organizationId}:dashboard"])->flush();
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

        if ($this->queryService->isDuplicateReference($organizationId, $reference)) {
            throw new DuplicateReferenceException(
                "A posted journal entry with reference '{$reference}' already exists in this organization."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

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

    /**
     * Validate that the sum of debits equals the sum of credits.
     *
     * Uses bcmath for precision — no floating-point rounding errors.
     *
     * @throws UnbalancedEntryException When debits ≠ credits
     * @throws InvalidEntryDataException When all amounts are zero
     */
    /**
     * @param  array<string, mixed>  $lines
     */
    private function validateBalance(array $lines): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $totalDebit = Money::add($totalDebit, (string) $line->debit);
            $totalCredit = Money::add($totalCredit, (string) $line->credit);
        }

        if (Money::compare($totalDebit, $totalCredit) !== 0) {
            throw new UnbalancedEntryException(
                "Entry is not balanced: debit={$totalDebit}, credit={$totalCredit}"
            );
        }

        if (Money::isZero($totalDebit)) {
            throw new InvalidEntryDataException('Journal entry must have non-zero amounts.');
        }
    }

    /**
     * Prevent posting entries into a closed fiscal year.
     *
     * Prefers the fiscal_years table (date-range based) so long fiscal
     * years (Swiss law: up to 23 months) work correctly. Falls back to
     * the legacy calendar-year `closed_fiscal_years` array on Organization
     * for organizations that have not yet been migrated.
     *
     * @throws FiscalYearClosedException
     */
    private function guardClosedFiscalYear(string $organizationId, string $date): void
    {
        $timestamp = strtotime($date);
        $isoDate = $timestamp !== false ? date('Y-m-d', $timestamp) : date('Y-m-d');
        $year = $timestamp !== false ? (int) date('Y', $timestamp) : (int) date('Y');

        $fiscalYear = FiscalYear::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->forDate($isoDate)
            ->first();

        if ($fiscalYear !== null) {
            if ($fiscalYear->isClosed()) {
                throw new FiscalYearClosedException($year);
            }

            return;
        }

        // Legacy fallback for orgs without fiscal_year records.
        $org = Organization::find($organizationId);

        if ($org && $org->isFiscalYearClosed($year)) {
            throw new FiscalYearClosedException($year);
        }
    }
}
