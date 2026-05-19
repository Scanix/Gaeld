<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Browses and verifies legally archived accounting documents.
 */
class LegalArchiveController extends Controller
{
    public function __construct(private readonly LegalArchivingService $service) {}

    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', LegalArchive::class);

        $archives = LegalArchive::query()
            ->orderByDesc('fiscal_year')
            ->orderBy('document_type')
            ->paginate(50)
            ->withQueryString();

        $archivesByYear = collect($archives->items())->groupBy('fiscal_year');

        return Inertia::render('Accounting/Archives/Index', [
            'archivesByYear' => $archivesByYear,
            'pagination' => $archives,
        ]);
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
