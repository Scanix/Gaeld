<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
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
    public function __construct(private readonly LegalArchivingService $service) {}

    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', LegalArchive::class);

        $years = DB::table('legal_archives')
            ->where('organization_id', $currentOrg->id())
            ->select('fiscal_year')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_count')
            ->selectRaw('MIN(expires_at) as earliest_expiry')
            ->groupBy('fiscal_year')
            ->orderByDesc('fiscal_year')
            ->get()
            ->map(fn ($row): array => [
                'fiscal_year' => (int) $row->fiscal_year,
                'total_count' => (int) $row->total_count,
                'verified_count' => (int) $row->verified_count,
                'earliest_expiry' => $row->earliest_expiry,
            ])
            ->all();

        return Inertia::render('Accounting/Archives/Index', [
            'years' => $years,
        ]);
    }

    public function forYear(int $year, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('viewAny', LegalArchive::class);

        $items = LegalArchive::query()
            ->where('fiscal_year', $year)
            ->orderBy('document_type')
            ->orderByDesc('archived_at')
            ->get()
            ->map(fn (LegalArchive $a) => [
                'id' => $a->id,
                'document_type' => $a->document_type,
                'document_id' => $a->document_id,
                'archived_at' => $a->archived_at->toIso8601String(),
                'expires_at' => $a->expires_at->toIso8601String(),
                'verified_at' => $a->verified_at?->toIso8601String(),
                'is_expiring_soon' => $a->isExpiringSoon(),
            ])
            ->all();

        return response()->json(['items' => $items]);
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

    public function download(LegalArchive $archive, CurrentOrganization $currentOrg): StreamedResponse
    {
        $this->authorize('view', $archive);

        return Storage::download(
            $archive->storage_path,
            basename($archive->storage_path)
        );
    }
}
