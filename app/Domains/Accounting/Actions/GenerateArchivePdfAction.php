<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates per-fiscal-year PDF archives (general journal, balance sheet,
 * P&L) alongside the existing JSON archive. Required for Swiss tax filing
 * (CO art. 957a) — the user submits the P&L PDF to the cantonal authority
 * every year. JSON + checksum satisfies the immutability requirement; the
 * PDF satisfies the human-readability requirement.
 *
 * The three artefacts are stored at:
 *   archives/{orgId}/{year}/pdf/{type}-{year}[-vN].pdf
 *
 * Each artefact has a matching LegalArchive row with a SHA-256 checksum,
 * indexed by (organization_id, document_type, document_id).
 */
final class GenerateArchivePdfAction
{
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
        private readonly FiscalYearService $fiscalYears,
    ) {}

    /**
     * Generate (or regenerate) the three PDF artefacts for the given year.
     *
     * @return array<int, array{type: string, path: string, checksum: string, regenerated: bool, version: int}>
     */
    public function execute(
        string $orgId,
        string|int $fiscalYear,
        ?string $fiscalYearId = null,
        bool $force = false,
    ): array {
        $org = Organization::findOrFail($orgId);
        $period = $this->fiscalYears->resolvePeriod(
            $org,
            $fiscalYearId,
            is_numeric((string) $fiscalYear) ? (int) $fiscalYear : null,
        );
        $fromDate = $period->fromDate;
        $toDate = $period->toDate;
        $storageLabel = $period->fiscalYearId
            ?? (Str::slug($period->label) ?: (string) $fiscalYear);

        return Cache::lock(
            "archive-pdf:{$orgId}:".($period->fiscalYearId ?? $period->label),
            600,
        )->block(10, function () use ($fromDate, $orgId, $org, $period, $storageLabel, $toDate, $force): array {
            $results = [];

            foreach (self::ARTEFACTS as $documentType => $slug) {
                $documentId = "pdf-{$storageLabel}";
                $existing = $this->latestArchive($orgId, $documentType, $documentId);
                $version = $existing === null ? 1 : $existing->version;
                $relativePath = $this->storagePath($orgId, $storageLabel, $slug, $version);

                // A sealed archive — file exists on disk — must never be overwritten.
                // The checksum is the integrity anchor; regenerating would silently
                // replace it with whatever the renderer produces today.
                if (! $force && $existing !== null && Storage::exists($relativePath)) {
                    $results[] = [
                        'type' => $documentType,
                        'path' => $relativePath,
                        'checksum' => $existing->checksum_sha256,
                        'regenerated' => false,
                        'version' => $existing->version,
                    ];

                    continue;
                }

                // Idempotent: skip if generated recently and not forced.
                if (! $force
                    && $existing !== null
                    && $existing->archived_at->diffInSeconds(now()) < 86_400
                    && Storage::exists($relativePath)
                ) {
                    $results[] = [
                        'type' => $documentType,
                        'path' => $relativePath,
                        'checksum' => $existing->checksum_sha256,
                        'regenerated' => false,
                        'version' => $existing->version,
                    ];

                    continue;
                }

                $version = $existing !== null ? $existing->version + 1 : 1;
                $relativePath = $this->storagePath($orgId, $storageLabel, $slug, $version);
                $content = $this->renderArtefact($documentType, $org, $fromDate, $toDate);
                $checksum = hash('sha256', $content);

                if (! Storage::put($relativePath, $content) || ! Storage::exists($relativePath)) {
                    throw new \RuntimeException("Unable to store archive PDF: {$relativePath}");
                }

                $now = now();
                LegalArchive::create([
                    'organization_id' => $orgId,
                    'document_type' => $documentType,
                    'document_id' => $documentId,
                    'version' => $version,
                    'fiscal_year' => (int) substr($fromDate, 0, 4),
                    'fiscal_year_id' => $period->fiscalYearId,
                    'checksum_sha256' => $checksum,
                    'storage_path' => $relativePath,
                    'archived_at' => $now,
                    'expires_at' => $now->copy()->addYears(10),
                    'verified_at' => null,
                ]);

                $results[] = [
                    'type' => $documentType,
                    'path' => $relativePath,
                    'checksum' => $checksum,
                    'regenerated' => true,
                    'version' => $version,
                ];
            }

            Log::info('Archive PDFs generated', [
                'organization_id' => $orgId,
                'fiscal_year_id' => $period->fiscalYearId,
                'fiscal_year' => $period->label,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'forced' => $force,
            ]);

            return $results;
        });
    }

    /**
     * Recover a missing or corrupted PDF file for an already-sealed archive.
     *
     * PDFs are non-deterministic (DomPDF embeds a generation timestamp), so
     * the byte-for-byte SHA-256 recorded at sealing time can never match a
     * regenerated PDF. The true integrity anchor for accounting data is the
     * companion JSON archive, which IS deterministic. For PDFs we therefore
     * regenerate unconditionally and append a new version, preserving the
     * previous file and checksum for auditability.
     */
    public function recoverFile(LegalArchive $archive): LegalArchive
    {
        $org = Organization::findOrFail($archive->organization_id);
        $period = $this->fiscalYears->resolvePeriod(
            $org,
            $archive->fiscal_year_id,
            $archive->fiscal_year,
        );
        $fromDate = $period->fromDate;
        $toDate = $period->toDate;
        $storageLabel = $period->fiscalYearId
            ?? (Str::slug($period->label) ?: (string) $archive->fiscal_year);
        $slug = self::ARTEFACTS[$archive->document_type]
            ?? throw new \InvalidArgumentException("Unknown PDF artefact: {$archive->document_type}");
        $latest = $this->latestArchive(
            $archive->organization_id,
            $archive->document_type,
            $archive->document_id,
        );
        $version = $latest === null ? $archive->version + 1 : $latest->version + 1;
        $relativePath = $this->storagePath($archive->organization_id, $storageLabel, $slug, $version);

        $content = $this->renderArtefact($archive->document_type, $org, $fromDate, $toDate);

        if (! Storage::put($relativePath, $content) || ! Storage::exists($relativePath)) {
            throw new \RuntimeException("Unable to store archive PDF: {$relativePath}");
        }

        return LegalArchive::create([
            'organization_id' => $archive->organization_id,
            'document_type' => $archive->document_type,
            'document_id' => $archive->document_id,
            'version' => $version,
            'fiscal_year' => $period->fiscalYearId !== null
                ? (int) substr($fromDate, 0, 4)
                : $archive->fiscal_year,
            'fiscal_year_id' => $period->fiscalYearId,
            'checksum_sha256' => hash('sha256', $content),
            'storage_path' => $relativePath,
            'archived_at' => now(),
            'expires_at' => now()->addYears(10),
            'verified_at' => null,
        ]);
    }

    private function latestArchive(string $orgId, string $documentType, string $documentId): ?LegalArchive
    {
        return LegalArchive::query()
            ->where('organization_id', $orgId)
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->orderByDesc('version')
            ->first();
    }

    private function storagePath(string $orgId, string $storageLabel, string $slug, int $version): string
    {
        $suffix = $version > 1 ? "-v{$version}" : '';

        return "archives/{$orgId}/{$storageLabel}/pdf/{$slug}-{$storageLabel}{$suffix}.pdf";
    }

    private function renderArtefact(string $documentType, Organization $org, string $fromDate, string $toDate): string
    {
        return match ($documentType) {
            'pdf_pnl' => $this->renderProfitAndLoss($org, $fromDate, $toDate),
            'pdf_balance_sheet' => $this->renderBalanceSheet($org, $fromDate, $toDate),
            'pdf_journal' => $this->renderJournal($org, $fromDate, $toDate),
            default => throw new \InvalidArgumentException("Unknown PDF artefact: {$documentType}"),
        };
    }

    private function renderProfitAndLoss(Organization $org, string $fromDate, string $toDate): string
    {
        $report = $this->reportingService->profitAndLoss($org->id, $fromDate, $toDate);

        return Pdf::loadView('exports.profit-and-loss', [
            'organization' => $org,
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'revenue' => $report['revenue'],
            'expenses' => $report['expenses'],
            'totalRevenue' => $report['total_revenue'],
            'totalExpenses' => $report['total_expenses'],
            'netProfit' => $report['net_profit'],
        ])->setPaper('A4', 'portrait')->output();
    }

    private function renderBalanceSheet(Organization $org, string $fromDate, string $asOfDate): string
    {
        $report = $this->reportingService->balanceSheet($org->id, $asOfDate);

        return Pdf::loadView('exports.balance-sheet', [
            'organization' => $org,
            'asOfDate' => $asOfDate,
            'period' => ['from' => $fromDate, 'to' => $asOfDate],
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
            'organization' => $org,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'entries' => $entries,
        ])->setPaper('A4', 'portrait')->output();
    }
}
