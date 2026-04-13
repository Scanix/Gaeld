<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\LettrageLot;
use App\Domains\Accounting\Models\TransactionLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Open-item lettrage (clearing) service for accounts receivable/payable.
 *
 * Groups balanced sets of transaction lines under a unique letter key
 * so that cleared items can be hidden from open-item reports.
 */
class LettrageService
{
    // ──────────────────────────────────────────────────────────────
    //  Lettering
    // ──────────────────────────────────────────────────────────────

    /**
     * Letter a set of transaction lines on the given account.
     *
     * All lines must belong to the given account and their net balance
     * (sum of debits minus sum of credits) must be zero.
     *
     * @param  int[]  $lineIds
     */
    public function letterLines(Account $account, array $lineIds, int $userId): LettrageLot
    {
        if (count($lineIds) < 2) {
            throw new \InvalidArgumentException('At least two transaction lines are required to letter.');
        }

        $lines = TransactionLine::whereIn('id', $lineIds)
            ->where('account_id', $account->id)
            ->get();

        if ($lines->count() !== count($lineIds)) {
            throw new \InvalidArgumentException('One or more transaction lines do not belong to this account.');
        }

        $alreadyLettered = $lines->filter(fn ($l) => $l->lettrage_key !== null);
        if ($alreadyLettered->isNotEmpty()) {
            throw new \InvalidArgumentException('One or more lines are already lettered.');
        }

        // Validate zero-sum: sum(debit) must equal sum(credit) using arbitrary-precision arithmetic
        $totalDebit = $lines->reduce(fn (string $carry, $l) => Money::add($carry, (string) ($l->debit ?? '0')), '0.00');
        $totalCredit = $lines->reduce(fn (string $carry, $l) => Money::add($carry, (string) ($l->credit ?? '0')), '0.00');

        if (Money::compare($totalDebit, $totalCredit) !== 0) {
            throw new \InvalidArgumentException(
                sprintf('Lines do not balance: debit=%s credit=%s', $totalDebit, $totalCredit)
            );
        }

        return DB::transaction(function () use ($account, $lineIds, $userId) {
            $key = $this->nextLetterKey($account);

            $lot = LettrageLot::create([
                'organization_id' => $account->organization_id,
                'account_id' => $account->id,
                'letter_key' => $key,
                'line_ids' => $lineIds,
                'lettered_by_user_id' => $userId,
                'lettered_at' => now(),
                'is_reversed' => false,
            ]);

            TransactionLine::whereIn('id', $lineIds)
                ->update(['lettrage_key' => $key]);

            Log::info('Lettrage applied', [
                'account_id' => $account->id,
                'letter_key' => $key,
                'line_count' => count($lineIds),
                'user_id' => $userId,
            ]);

            return $lot;
        });
    }

    /**
     * Reverse a lettrage lot, clearing the letter key from all its lines.
     */
    public function unletter(LettrageLot $lot): void
    {
        if ($lot->is_reversed) {
            throw new \InvalidArgumentException('This lettrage lot is already reversed.');
        }

        DB::transaction(function () use ($lot) {
            TransactionLine::whereIn('id', $lot->line_ids)
                ->update(['lettrage_key' => null]);

            $lot->update(['is_reversed' => true]);

            Log::info('Lettrage reversed', [
                'lot_id' => $lot->id,
                'account_id' => $lot->account_id,
                'letter_key' => $lot->letter_key,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  Queries
    // ──────────────────────────────────────────────────────────────

    /**
     * Get all unlettered transaction lines for the given account.
     *
     * @return Collection<int, TransactionLine>
     */
    public function getOpenItems(Account $account, ?string $upToDate = null): Collection
    {
        $query = TransactionLine::where('account_id', $account->id)
            ->whereNull('lettrage_key')
            ->with('journalEntry');

        if ($upToDate) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('date', '<=', $upToDate));
        }

        return $query->orderBy('id')->get();
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Determine the next available letter key for an account.
     *
     * Sequence: A, B, …, Z, AA, AB, …, AZ, BA, …, ZZ
     */
    public function nextLetterKey(Account $account): string
    {
        $existing = LettrageLot::where('account_id', $account->id)
            ->where('is_reversed', false)
            ->orderByDesc('id')
            ->value('letter_key');

        if ($existing === null) {
            return 'A';
        }

        return $this->incrementKey($existing);
    }

    private function incrementKey(string $key): string
    {
        $chars = str_split($key);
        $carry = true;
        $i = count($chars) - 1;

        while ($carry && $i >= 0) {
            $ord = ord($chars[$i]);
            if ($ord < ord('Z')) {
                $chars[$i] = chr($ord + 1);
                $carry = false;
            } else {
                $chars[$i] = 'A';
                $i--;
            }
        }

        if ($carry) {
            array_unshift($chars, 'A');
        }

        return implode('', $chars);
    }
}
