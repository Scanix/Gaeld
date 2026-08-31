<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Exceptions\VatPeriodLockedException;
use App\Domains\Accounting\Models\JournalEntry;

final class VatPeriodLockService
{
    public function assertUnlockedForJournalEntry(string $journalEntryId): void
    {
        $journalEntry = JournalEntry::withoutGlobalScopes()
            ->whereKey($journalEntryId)
            ->first(['id', 'organization_id', 'date']);

        if ($journalEntry === null || ! $this->isLocked($journalEntry->organization_id, $journalEntry->date->toDateString())) {
            return;
        }

        throw new VatPeriodLockedException($journalEntry->date->toDateString());
    }

    public function isLocked(string $organizationId, string $date): bool
    {
        $settlements = JournalEntry::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('type', 'vat_settlement')
            ->where('is_posted', true)
            ->whereDate('vat_period_start', '<=', $date)
            ->whereDate('vat_period_end', '>=', $date)
            ->get(['reference']);

        foreach ($settlements as $settlement) {
            $hasPostedReversal = JournalEntry::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('reference', 'REV-'.$settlement->reference)
                ->where('is_posted', true)
                ->exists();

            if (! $hasPostedReversal) {
                return true;
            }
        }

        return false;
    }

    public function assertPeriodUnlocked(string $organizationId, string $fromDate, string $toDate): void
    {
        $overlappingSettlementExists = JournalEntry::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('type', 'vat_settlement')
            ->where('is_posted', true)
            ->whereDate('vat_period_start', '<=', $toDate)
            ->whereDate('vat_period_end', '>=', $fromDate)
            ->get(['reference'])
            ->contains(function (JournalEntry $settlement) use ($organizationId): bool {
                return ! JournalEntry::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('reference', 'REV-'.$settlement->reference)
                    ->where('is_posted', true)
                    ->exists();
            });

        if ($overlappingSettlementExists) {
            throw new VatPeriodLockedException($fromDate);
        }
    }
}
