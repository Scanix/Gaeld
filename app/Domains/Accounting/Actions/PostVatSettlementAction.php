<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\VatPeriodLockService;
use App\Domains\Accounting\Services\VatReportService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts the VAT settlement clearing journal entry.
 *
 * Accounting logic:
 *   Debit  2200 (TVA collectée / VAT Output) — clears the output VAT liability
 *   Credit 1170 (TVA déductible / VAT Input) — clears the input VAT asset
 *   Credit 2201 (TVA à payer / VAT payable)  — net amount payable to AFC
 *   (or Debit 2201 if net is negative, i.e. a refund is due)
 */
final class PostVatSettlementAction
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly LedgerQueryService $ledgerQuery,
        private readonly VatReportService $vatReportService,
        private readonly VatPeriodLockService $vatPeriodLocks,
    ) {}

    public function execute(string $orgId, string $fromDate, string $toDate, ?int $lockedByUserId = null): JournalEntry
    {
        $this->vatPeriodLocks->assertPeriodUnlocked($orgId, $fromDate, $toDate);

        $report = $this->vatReportService->generateFresh($orgId, $fromDate, $toDate);

        $totalOutputVat = $report['total_output_vat'];
        $totalInputVat = $report['input_vat'];
        $netVat = $report['net_vat'];

        $vatOutputAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::VAT_OUTPUT);
        $vatInputAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::VAT_INPUT);
        $vatSettlementAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::VAT_PAYABLE_AFC);

        // Build balanced lines
        $lines = [];

        // Debit 2200 — clears output VAT collected
        $lines[] = new JournalLineData(
            accountId: (string) $vatOutputAccount->id,
            debit: $totalOutputVat,
            credit: '0.00',
            description: 'VAT settlement: output VAT cleared',
        );

        // Credit 1170 — clears input VAT recoverable
        $lines[] = new JournalLineData(
            accountId: (string) $vatInputAccount->id,
            debit: '0.00',
            credit: $totalInputVat,
            description: 'VAT settlement: input VAT cleared',
        );

        // Net to 2201 (positive → credit = payable; negative → debit = refund due)
        if (! Money::isNegative($netVat)) {
            $lines[] = new JournalLineData(
                accountId: (string) $vatSettlementAccount->id,
                debit: '0.00',
                credit: $netVat,
                description: 'VAT settlement: net VAT payable to AFC',
            );
        } else {
            $lines[] = new JournalLineData(
                accountId: (string) $vatSettlementAccount->id,
                debit: Money::negate($netVat),
                credit: '0.00',
                description: 'VAT settlement: VAT refund due from AFC',
            );
        }

        $reference = $this->resolveReference($orgId, $fromDate, $toDate);

        return DB::transaction(function () use ($orgId, $reference, $toDate, $fromDate, $lines, $totalOutputVat, $totalInputVat, $netVat, $lockedByUserId): JournalEntry {
            $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
                date: $toDate,
                reference: $reference,
                description: "VAT settlement for period {$fromDate} to {$toDate}",
                lines: $lines,
            ));

            $journalEntry->update([
                'type' => 'vat_settlement',
                'vat_period_start' => $fromDate,
                'vat_period_end' => $toDate,
                'vat_period_locked_at' => now(),
                'vat_period_locked_by_user_id' => $lockedByUserId,
            ]);

            Log::info('VAT settlement posted', [
                'organization_id' => $orgId,
                'period' => "{$fromDate} to {$toDate}",
                'reference' => $reference,
                'output_vat' => $totalOutputVat,
                'input_vat' => $totalInputVat,
                'net_vat' => $netVat,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $journalEntry;
        });
    }

    /**
     * The settlement reference is deterministic per period so re-posting the
     * same period is normally blocked as a duplicate. If the original
     * settlement was reversed (a corrected/re-posted period), fall back to a
     * versioned suffix so the new entry can be posted under a fresh reference.
     */
    private function resolveReference(string $orgId, string $fromDate, string $toDate): string
    {
        $base = "VAT-SETTLEMENT-{$fromDate}-{$toDate}";

        if (! JournalEntry::where('organization_id', $orgId)->where('reference', $base)->exists()) {
            return $base;
        }

        $version = 2;
        while (JournalEntry::where('organization_id', $orgId)->where('reference', "{$base}-v{$version}")->exists()) {
            $version++;
        }

        return "{$base}-v{$version}";
    }
}
