<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\DTOs\FiscalYearPeriod;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Browses and verifies legally archived accounting documents.
 *
 * The index renders one accordion per fiscal year with aggregate
 * stats only; per-year rows are lazy-loaded via {@see forYear()}.
 * This replaces the previous "Page N / M" row-level pagination,
 * which was visually noisy once the archive count grew past 50.
 */
class LegalArchiveController extends Controller
{
    public function __construct(
        private readonly LegalArchivingService $service,
        private readonly FiscalYearService $fiscalYears,
    ) {}

    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', LegalArchive::class);
        app(ArchiveAccessLog::class)->record($currentOrg->id(), null, 'historical_archives.viewed', $request->user());

        $archivedYears = DB::table('legal_archives')
            ->where('organization_id', $currentOrg->id())
            ->select('fiscal_year', 'fiscal_year_id')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_count')
            ->selectRaw('MIN(expires_at) as earliest_expiry')
            ->groupBy('fiscal_year', 'fiscal_year_id')
            ->orderByDesc('fiscal_year')
            ->get()
            ->keyBy('fiscal_year');

        $closedFiscalYears = $currentOrg->get()->closed_fiscal_years ?? [];

        $unarchivedYears = array_diff($closedFiscalYears, $archivedYears->keys()->all());

        $organization = $currentOrg->get();

        $years = $archivedYears
            ->map(function ($row) use ($organization): array {
                $period = $this->fiscalYears->resolvePeriod(
                    $organization,
                    $row->fiscal_year_id,
                    (int) $row->fiscal_year,
                );

                return [
                    'fiscal_year' => (int) $row->fiscal_year,
                    'fiscal_year_id' => $period->fiscalYearId,
                    'start_date' => $period->fromDate,
                    'end_date' => $period->toDate,
                    'total_count' => (int) $row->total_count,
                    'verified_count' => (int) $row->verified_count,
                    'earliest_expiry' => $row->earliest_expiry,
                ];
            })
            ->values()
            ->concat(
                collect($unarchivedYears)->map(function (int $year) use ($organization): array {
                    $period = $this->fiscalYears->resolvePeriod($organization, null, $year);

                    return [
                        'fiscal_year' => $year,
                        'fiscal_year_id' => $period->fiscalYearId,
                        'start_date' => $period->fromDate,
                        'end_date' => $period->toDate,
                        'total_count' => 0,
                        'verified_count' => 0,
                        'earliest_expiry' => null,
                    ];
                })
            )
            ->sortByDesc('fiscal_year')
            ->values()
            ->all();

        return Inertia::render('Accounting/Archives/Index', [
            'years' => $years,
            'canManage' => $request->user()->can('create', LegalArchive::class),
        ]);
    }

    public function forYear(int $year, Request $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('viewAny', LegalArchive::class);

        $period = $this->resolveRequestedPeriod($request, $currentOrg, $year);
        $items = $this->periodQuery($period)
            ->orderBy('document_type')
            ->orderByDesc('archived_at')
            ->get()
            ->map(fn (LegalArchive $a) => [
                'id' => $a->id,
                'document_type' => $a->document_type,
                'document_id' => $a->document_id,
                'version' => $a->version,
                'archived_at' => $a->archived_at->toIso8601String(),
                'expires_at' => $a->expires_at->toIso8601String(),
                'verified_at' => $a->verified_at?->toIso8601String(),
                'is_expiring_soon' => $a->isExpiringSoon(),
            ])
            ->all();

        return response()->json([
            'period' => [
                'id' => $period->fiscalYearId,
                'label' => $period->label,
                'start_date' => $period->fromDate,
                'end_date' => $period->toDate,
                'is_legacy_fallback' => $period->isLegacyFallback,
            ],
            'items' => $items,
        ]);
    }

    public function generateForYear(int $year, Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', LegalArchive::class);

        $org = $currentOrg->get();
        $period = $this->resolveRequestedPeriod($request, $currentOrg, $year);

        $fiscalYear = $period->fiscalYearId
            ? FiscalYear::query()->whereKey($period->fiscalYearId)->first()
            : null;
        abort_unless($fiscalYear?->isClosed() ?? $org->isFiscalYearClosed($year), 403, __('app.fiscal_year_not_closed'));

        $this->service->archiveFiscalYear($currentOrg->id(), $period->label, $period->fiscalYearId);

        return redirect()->route('accounting.archives.index')
            ->with('success', __('app.archive_generated'));
    }

    public function verify(LegalArchive $archive, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('view', $archive);

        $ok = $this->service->verifyIntegrity($archive);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? __('app.archive_integrity_ok') : __('app.archive_integrity_failed')
        );
    }

    public function download(LegalArchive $archive, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('view', $archive);
        app(ArchiveAccessLog::class)->record($archive->organization_id, null, 'historical_archive.download_authorized', auth()->user(), ['archive_id' => $archive->id]);

        return redirect(URL::temporarySignedRoute(
            'accounting.archives.file',
            now()->addMinutes(5),
            ['archive' => $archive->id],
        ));
    }

    /**
     * Serve the archive file via a temporary signed URL.
     *
     * This route carries only `ValidateSignature` middleware — no session
     * required — so the file streams correctly even when opened in a new
     * browser tab where session cookies are not sent.
     */
    public function serveFile(LegalArchive $archive): StreamedResponse
    {
        // Authorization is guaranteed by the signed URL generated in
        // download() or downloadPdf() after an authenticated policy check.
        abort_unless(Storage::exists($archive->storage_path), 409, 'The historical original is missing. Restore it from a verified backup.');
        abort_unless($this->service->verifyIntegrity($archive), 409, 'The historical original failed its integrity check.');
        app(ArchiveAccessLog::class)->record($archive->organization_id, null, 'historical_archive.downloaded', auth()->user(), [
            'archive_id' => $archive->id, 'access' => 'temporary_signed_link',
        ]);

        return Storage::download(
            $archive->storage_path,
            basename($archive->storage_path)
        );
    }

    /**
     * Stream one of the three per-year PDF artefacts (pnl, balance_sheet, journal).
     *
     * Generates on-demand if it doesn't exist yet so freelancers can always
     * retrieve their Swiss tax filing without going through a closing.
     */
    public function downloadPdf(int $year, string $type, Request $request, CurrentOrganization $currentOrg, GenerateArchivePdfAction $pdfAction): RedirectResponse
    {
        $this->authorize('viewAny', LegalArchive::class);

        $documentType = $this->resolveDocumentType($type);
        $period = $this->resolveRequestedPeriod($request, $currentOrg, $year);
        $documentId = $this->pdfDocumentId($period);

        $archive = $this->periodQuery($period)
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->orderByDesc('version')
            ->first();

        if ($archive !== null && Storage::exists($archive->storage_path)) {
            // Existing original is preserved; proof level is recorded separately.
        } elseif ($archive === null) {
            // First generation (open year or on-demand for a freelancer).
            $pdfAction->execute($currentOrg->id(), $period->label, $period->fiscalYearId);
            $archive = $this->periodQuery($period)
                ->where('document_type', $documentType)
                ->where('document_id', $documentId)
                ->firstOrFail();
        } else {
            app(ArchiveAccessLog::class)->record($archive->organization_id, null, 'historical_archive.missing', $request->user(), ['archive_id' => $archive->id]);
            abort(409, 'The historical original is missing. Restore the original; a new version cannot replace it.');
        }

        return redirect(URL::temporarySignedRoute(
            'accounting.archives.file',
            now()->addMinutes(5),
            ['archive' => $archive->id],
        ));
    }

    /**
     * Stream a ZIP bundle containing the three per-year PDFs.
     */
    public function downloadYearBundle(int $year, Request $request, CurrentOrganization $currentOrg, GenerateArchivePdfAction $pdfAction): BinaryFileResponse
    {
        $this->authorize('viewAny', LegalArchive::class);

        $period = $this->resolveRequestedPeriod($request, $currentOrg, $year);
        if (! $this->periodQuery($period)->whereIn('document_type', ['pdf_pnl', 'pdf_balance_sheet', 'pdf_journal'])->exists()) {
            $pdfAction->execute($currentOrg->id(), $period->label, $period->fiscalYearId);
        }

        $archives = $this->periodQuery($period)
            ->whereIn('document_type', ['pdf_pnl', 'pdf_balance_sheet', 'pdf_journal'])
            ->where('document_id', $this->pdfDocumentId($period))
            ->get()
            ->groupBy('document_type')
            ->map(fn ($versions) => $versions->sortByDesc('version')->first());

        abort_unless($archives->count() === 3, 409, 'The historical report bundle is incomplete.');
        foreach ($archives as $archive) {
            abort_unless(Storage::exists($archive->storage_path) && $this->service->verifyIntegrity($archive), 409, 'A historical report is missing or altered.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'archive-bundle-').'.zip';
        $zip = new \ZipArchive;
        if ($zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to open ZIP archive');
        }

        foreach ($archives as $archive) {
            $zip->addFromString(basename($archive->storage_path), Storage::get($archive->storage_path));
        }

        $zip->close();
        app(ArchiveAccessLog::class)->record($currentOrg->id(), null, 'historical_bundle.downloaded', $request->user(), ['archive_ids' => $archives->pluck('id')->all()]);

        return response()->download(
            $tmpPath,
            sprintf('archive-%d.zip', $year),
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend();
    }

    /**
     * Force regeneration of the three per-year PDF artefacts.
     */
    public function regeneratePdfs(int $year, Request $request, CurrentOrganization $currentOrg, GenerateArchivePdfAction $pdfAction): RedirectResponse
    {
        $this->authorize('create', LegalArchive::class);

        $period = $this->resolveRequestedPeriod($request, $currentOrg, $year);
        $archives = $this->periodQuery($period)
            ->whereIn('document_type', ['pdf_pnl', 'pdf_balance_sheet', 'pdf_journal'])
            ->where('document_id', $this->pdfDocumentId($period))
            ->get()
            ->groupBy('document_type')
            ->map(fn ($versions) => $versions->sortByDesc('version')->first())
            ->keyBy('document_type');

        if ($archives->isEmpty()) {
            // Nothing archived yet — generate fresh.
            $pdfAction->execute($currentOrg->id(), $period->label, $period->fiscalYearId);

            return back()->with('success', __('app.archive_pdfs_regenerated'));
        }

        // Explicit regeneration creates new versions and preserves the historical originals.
        $archives->each(fn (LegalArchive $a) => $pdfAction->recoverFile($a));

        return back()->with('success', __('app.archive_pdfs_regenerated'));
    }

    /**
     * Map the public route parameter to the LegalArchive `document_type` value.
     */
    private function resolveDocumentType(string $type): string
    {
        return match ($type) {
            'pnl' => 'pdf_pnl',
            'balance_sheet' => 'pdf_balance_sheet',
            'journal' => 'pdf_journal',
            default => throw new \InvalidArgumentException("Unknown archive PDF type: {$type}"),
        };
    }

    /** @return Builder<LegalArchive> */
    private function periodQuery(FiscalYearPeriod $period): Builder
    {
        return LegalArchive::query()
            ->when(
                $period->fiscalYearId !== null,
                fn (Builder $query): Builder => $query->where('fiscal_year_id', $period->fiscalYearId),
                fn (Builder $query): Builder => $query->where('fiscal_year', (int) $period->label),
            );
    }

    private function pdfDocumentId(FiscalYearPeriod $period): string
    {
        return 'pdf-'.($period->fiscalYearId ?? (Str::slug($period->label) ?: $period->label));
    }

    private function resolveRequestedPeriod(Request $request, CurrentOrganization $currentOrg, int $year): FiscalYearPeriod
    {
        return $this->fiscalYears->resolvePeriod(
            $currentOrg->get(),
            $request->query('fiscal_year_id'),
            $year,
        );
    }
}
