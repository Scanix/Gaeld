<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only ledger queries: balances, trial balance, account lookups.
 *
 * Separated from LedgerService (which handles writes) so that
 * reporting, dashboard, and reconciliation code can depend on
 * queries without pulling in the full posting contract.
 */
class LedgerQueryService
{
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
     * @return array<array{account_code: string, account_name: string, account_type: AccountType|string, debit: string, credit: string}>
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

    private function isDebitNormalAccount(AccountType|string $type): bool
    {
        if ($type instanceof AccountType) {
            return $type->isDebitNormal();
        }

        return AccountType::from($type)->isDebitNormal();
    }
}
