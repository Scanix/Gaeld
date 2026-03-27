<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegalArchiveController extends Controller
{
    public function __construct(private readonly LegalArchivingService $service) {}

    public function index(CurrentOrganization $currentOrg): Response
    {
        $orgId = $currentOrg->id();

        $archives = LegalArchive::where('organization_id', $orgId)
            ->orderByDesc('fiscal_year')
            ->orderBy('document_type')
            ->get()
            ->groupBy('fiscal_year');

        return Inertia::render('Accounting/Archives/Index', [
            'archivesByYear' => $archives,
        ]);
    }

    public function verify(LegalArchive $archive, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorizeForOrg($archive, $currentOrg);

        $ok = $this->service->verifyIntegrity($archive);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? __('app.archive_integrity_ok') : __('app.archive_integrity_failed')
        );
    }

    public function download(LegalArchive $archive, CurrentOrganization $currentOrg): StreamedResponse
    {
        $this->authorizeForOrg($archive, $currentOrg);

        return Storage::download(
            $archive->storage_path,
            basename($archive->storage_path)
        );
    }

    private function authorizeForOrg(LegalArchive $archive, CurrentOrganization $currentOrg): void
    {
        abort_unless($archive->organization_id === $currentOrg->id(), 403);
    }
}
