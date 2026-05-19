<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Generates per-fiscal-year PDF archives (general journal, balance sheet,
 * P&L) alongside the existing JSON archive. Required for Swiss tax filing
 * (CO art. 957a) — the user submits the P&L PDF to the cantonal authority
 * every year. JSON + checksum satisfies the immutability requirement; the
 * PDF satisfies the human-readability requirement.
 *
 * The three artefacts are stored at:
 *   archives/{orgId}/{year}/pdf/{type}-{year}.pdf
 *
 * Each artefact has a matching LegalArchive row with a SHA-256 checksum,
 * indexed by (organization_id, document_type, document_id).
 */
final class GenerateArchivePdfAction
{
    /**
     * If a PDF was generated within this many seconds, regeneration is a no-op.
     */
    private const REGENERATION_COOLDOWN_SECONDS = 86_400; // 1 day

    /**
     * @var array<string, string> document_type => filename slug
     */
    private const ARTEFACTS = [
        'pdf_pnl' => 'pnl',
        'pdf_balance_sheet' => 'balance-sheet',
        'pdf_journal' => 'journal',
    ];

    public function __construct(
        private readonly ReportingService $reportingService,
    ) {}

    /**
     * Generate (or regenerate) the three PDF artefacts for the given year.
     *
     * @return array<int, array{type: string, path: string, checksum: string, regenerated: bool}>
     */
    public function execute(string $orgId, int $year, bool $force = false): array
    {
        $org = Organization::findOrFail($orgId);
        $fromDate = sprintf('%04d-01-01', $year);
        $toDate = sprintf('%04d-12-31', $year);

        $results = [];

        foreach (self::ARTEFACTS as $documentType => $slug) {
            $relativePath = "archives/{$orgId}/{$year}/pdf/{$slug}-{$year}.pdf";

            $existing = LegalArchive::query()
                ->where('organization_id', $orgId)
                ->where('document_type', $documentType)
                ->where('document_id', "pdf-{$year}")
                ->first();

            // Idempotent: skip if generated recently and not forced.
            if (! $force
                && $existing !== null
                && $existing->archived_at->diffInSeconds(now()) < self::REGENERATION_COOLDOWN_SECONDS
                && Storage::exists($relativePath)
            ) {
                $results[] = [
                    'type' => $documentType,
                    'path' => $relativePath,
                    'checksum' => $existing->checksum_sha256,
                    'regenerated' => false,
                ];

                continue;
            }

            $content = $this->renderArtefact($documentType, $org, $fromDate, $toDate);
            $checksum = hash('sha256', $content);

            Storage::put($relativePath, $content);

            $now = now();
            LegalArchive::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'document_type' => $documentType,
                    'document_id' => "pdf-{$year}",
                ],
                [
                    'fiscal_year' => $year,
                    'checksum_sha256' => $checksum,
                    'storage_path' => $relativePath,
                    'archived_at' => $now,
                    'expires_at' => $now->copy()->addYears(10),
                    'verified_at' => null,
                ]
            );

            $results[] = [
                'type' => $documentType,
                'path' => $relativePath,
                'checksum' => $checksum,
                'regenerated' => true,
            ];
        }

        return $results;
    }

    private function renderArtefact(string $documentType, Organization $org, string $fromDate, string $toDate): string
    {
        return match ($documentType) {
            'pdf_pnl' => $this->renderProfitAndLoss($org, $fromDate, $toDate),
            'pdf_balance_sheet' => $this->renderBalanceSheet($org, $toDate),
            'pdf_journal' => $this->renderJournal($org, $fromDate, $toDate),
            default => throw new \InvalidArgumentException("Unknown PDF artefact: {$documentType}"),
        };
    }

    private function renderProfitAndLoss(Organization $org, string $fromDate, string $toDate): string
    {
        $report = $this->reportingService->profitAndLoss($org->id, $fromDate, $toDate);

        return Pdf::loadView('exports.profit-and-loss', [
            'organizationName' => $org->name,
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'revenue' => $report['revenue'],
            'expenses' => $report['expenses'],
            'totalRevenue' => $report['total_revenue'],
            'totalExpenses' => $report['total_expenses'],
            'netProfit' => $report['net_profit'],
        ])->setPaper('A4', 'portrait')->output();
    }

    private function renderBalanceSheet(Organization $org, string $asOfDate): string
    {
        $report = $this->reportingService->balanceSheet($org->id, $asOfDate);

        return Pdf::loadView('exports.balance-sheet', [
            'organizationName' => $org->name,
            'asOfDate' => $asOfDate,
            'assets' => $report['assets'],
            'liabilities' => $report['liabilities'],
            'equity' => $report['equity'],
        ])->setPaper('A4', 'portrait')->output();
    }

    private function renderJournal(Organization $org, string $fromDate, string $toDate): string
    {
        $entries = JournalEntry::query()
            ->where('organization_id', $org->id)
            ->where('is_posted', true)
            ->whereBetween('date', [$fromDate, $toDate])
            ->with('lines.account')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return Pdf::loadView('exports.journal-entries', [
            'organizationName' => $org->name,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'entries' => $entries,
        ])->setPaper('A4', 'portrait')->output();
    }
}
