<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\VatReportService;
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
    ) {}

    public function execute(string $orgId, string $fromDate, string $toDate): JournalEntry
    {
        $report = $this->vatReportService->generate($orgId, $fromDate, $toDate);

        $totalOutputVat = $report['total_output_vat'];
        $totalInputVat = $report['input_vat'];
        $netVat = $report['net_vat'];

        $vatOutputAccount = $this->ledgerQuery->resolveAccount($orgId, '2200');
        $vatInputAccount = $this->ledgerQuery->resolveAccount($orgId, '1170');
        $vatSettlementAccount = $this->ledgerQuery->resolveAccount($orgId, '2201');

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
        if (bccomp($netVat, '0', 2) >= 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $vatSettlementAccount->id,
                debit: '0.00',
                credit: $netVat,
                description: 'VAT settlement: net VAT payable to AFC',
            );
        } else {
            $lines[] = new JournalLineData(
                accountId: (string) $vatSettlementAccount->id,
                debit: bcmul($netVat, '-1', 2),
                credit: '0.00',
                description: 'VAT settlement: VAT refund due from AFC',
            );
        }

        $reference = "VAT-SETTLEMENT-{$fromDate}-{$toDate}";

        $journalEntry = $this->ledgerService->postEntry($orgId, new JournalEntryData(
            date: now()->toDateString(),
            reference: $reference,
            description: "VAT settlement for period {$fromDate} to {$toDate}",
            lines: $lines,
        ));

        $journalEntry->update(['type' => 'vat_settlement']);

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
    }
}
