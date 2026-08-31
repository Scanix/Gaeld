<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Services\ReportingService;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TCPDF;

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

                unset($content);
                gc_collect_cycles();
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
        $checksum = hash('sha256', $content);

        if (! Storage::put($relativePath, $content) || ! Storage::exists($relativePath)) {
            throw new \RuntimeException("Unable to store archive PDF: {$relativePath}");
        }

        unset($content);
        gc_collect_cycles();

        return LegalArchive::create([
            'organization_id' => $archive->organization_id,
            'document_type' => $archive->document_type,
            'document_id' => $archive->document_id,
            'version' => $version,
            'fiscal_year' => $period->fiscalYearId !== null
                ? (int) substr($fromDate, 0, 4)
                : $archive->fiscal_year,
            'fiscal_year_id' => $period->fiscalYearId,
            'checksum_sha256' => $checksum,
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
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);
        $pdf->writeHTML($this->journalHeaderHtml($org, $fromDate, $toDate), true, false, true, false, '');

        JournalEntry::query()
            ->select(['id', 'date', 'reference', 'description'])
            ->where('organization_id', $org->id)
            ->where('is_posted', true)
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->orderBy('id')
            ->chunk(100, function ($entries) use ($pdf): void {
                $entries->load([
                    'lines' => fn ($query) => $query->select(['id', 'journal_entry_id', 'account_id', 'debit', 'credit', 'description']),
                    'lines.account' => fn ($query) => $query->select(['id', 'code', 'name']),
                ]);

                $html = '';
                foreach ($entries as $entry) {
                    $html .= $this->journalEntryHtml($entry);
                }

                if ($html !== '') {
                    $pdf->writeHTML($html, true, false, true, false, '');
                }

                unset($entries);
                unset($html);
                gc_collect_cycles();
            });

        $pdf->writeHTML(
            '<p style="margin-top: 20px; color: #999; text-align: center;">'.e(__('exports.common.generated_on')).' '.now()->format('d.m.Y H:i').' — '.e($org->name).'</p>',
            true,
            false,
            true,
            false,
            '',
        );

        $content = $pdf->Output('', 'S');
        unset($pdf);
        gc_collect_cycles();

        return $content;
    }

    private function journalHeaderHtml(Organization $org, string $fromDate, string $toDate): string
    {
        return '<h1 style="text-align: center; font-size: 16pt;">'.e($org->name).'</h1>'
            .'<p style="text-align: center; color: #555;">'.e(__('exports.journal_entries.period', ['from' => $fromDate, 'to' => $toDate])).'</p>'
            .'<table border="1" cellpadding="4" cellspacing="0" width="100%">'
            .'<thead><tr style="background-color: #f0f0f0;">'
            .'<th>'.e(__('exports.common.date')).'</th>'
            .'<th>'.e(__('exports.common.reference')).'</th>'
            .'<th>'.e(__('exports.common.description_account')).'</th>'
            .'<th align="right">'.e(__('exports.common.debit')).'</th>'
            .'<th align="right">'.e(__('exports.common.credit')).'</th>'
            .'</tr></thead></table>';
    }

    private function journalEntryHtml(JournalEntry $entry): string
    {
        $date = $entry->date->format('d.m.Y');
        $html = '<table border="0" cellpadding="3" cellspacing="0" width="100%">'
            .'<tr style="background-color: #f8f8f8; font-weight: bold;">'
            .'<td>'.e($date).'</td>'
            .'<td>'.e($entry->reference).'</td>'
            .'<td>'.e($entry->description).'</td><td></td><td></td></tr>';

        foreach ($entry->lines as $line) {
            $html .= '<tr>'
                .'<td></td><td>'.e($line->account->code).'</td>'
                .'<td>'.e($line->account->name).($line->description ? ' - '.e($line->description) : '').'</td>'
                .'<td align="right">'.e($this->journalAmount((string) $line->debit)).'</td>'
                .'<td align="right">'.e($this->journalAmount((string) $line->credit)).'</td></tr>';
        }

        return $html.'</table>';
    }

    private function journalAmount(string $amount): string
    {
        return ! Money::isZero($amount)
            ? number_format((float) $amount, 2, '.', "'")
            : '';
    }
}
