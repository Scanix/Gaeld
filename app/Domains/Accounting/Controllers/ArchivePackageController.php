<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Requests\ArchivePackageRequest;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchivePackageController extends Controller
{
    public function __construct(
        private readonly ArchivePackageService $packages,
        private readonly ArchiveAccessLog $audit,
    ) {}

    public function index(Request $request, CurrentOrganization $organization): Response
    {
        $this->authorize('viewAny', ArchivePackage::class);
        $query = ArchivePackage::query()->where('organization_id', $organization->id());
        $this->audit->record($organization->id(), null, 'packages.viewed', $request->user());

        return Inertia::render('Accounting/Archives/Packages', [
            'packages' => (clone $query)->with('fiscalYear')->latest('captured_at')->paginate(25)
                ->through(fn (ArchivePackage $package): array => [
                    'id' => $package->id,
                    'fiscal_year' => $package->fiscalYear?->name,
                    'from' => $package->fiscalYear?->start_date->toDateString(),
                    'to' => $package->fiscalYear?->end_date->toDateString(),
                    'version' => $package->version,
                    'state' => $package->state,
                    'provenance' => $package->provenance,
                    'archival_pdf' => $package->archival_pdf,
                    'captured_at' => $package->captured_at->toIso8601String(),
                    'verified_at' => $package->verified_at?->toIso8601String(),
                    'retention_until' => $package->retention_until->toIso8601String(),
                    'legal_hold' => $package->legal_hold,
                    'available' => $package->manifest_sha256 !== null,
                    'has_incident' => $package->failure !== null,
                ]),
            'fiscalYears' => FiscalYear::query()->where('organization_id', $organization->id())
                ->orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date']),
            'canManage' => $request->user()->can('create', ArchivePackage::class),
            'summary' => [
                'pending' => (clone $query)->whereIn('state', ['pending', 'preparing'])->count(),
                'incidents' => (clone $query)->whereIn('state', ['failed', 'incident'])->count(),
                'overdue' => (clone $query)->where(function ($query): void {
                    $query->whereNotNull('manifest_sha256')->where(function ($query): void {
                        $query->whereNull('verified_at')->orWhere('verified_at', '<', now()->subDay());
                    });
                })->count(),
            ],
        ]);
    }

    public function store(ArchivePackageRequest $request, CurrentOrganization $organization): RedirectResponse
    {
        $year = FiscalYear::query()->where('organization_id', $organization->id())
            ->whereKey((string) $request->validated('fiscal_year_id'))->firstOrFail();
        $this->packages->request($organization->get(), $year, $request->user(), 'native', $request->boolean('archival_pdf'));

        return back()->with('success', __('app.conservation_requested'));
    }

    public function resume(ArchivePackage $package, Request $request, CurrentOrganization $organization): RedirectResponse
    {
        $this->authorizePackage($package, $organization, true);
        $this->packages->retry($package);
        $this->audit->record($organization->id(), $package->id, 'package.retry_requested', $request->user());

        return back()->with('success', __('app.conservation_requested'));
    }

    public function verify(ArchivePackage $package, Request $request, CurrentOrganization $organization): RedirectResponse
    {
        $this->authorizePackage($package, $organization);
        $result = $this->packages->verify($package);
        $this->audit->record($organization->id(), $package->id, 'package.integrity_checked', $request->user(), $result);
        $valid = $result['valid'];

        return back()->with($valid ? 'success' : 'error', __($valid ? 'app.archive_integrity_ok' : 'app.archive_integrity_failed'));
    }

    public function export(ArchivePackage $package, Request $request, CurrentOrganization $organization): BinaryFileResponse
    {
        $this->authorizePackage($package, $organization);
        $path = $this->packages->export($package);
        $this->audit->record($organization->id(), $package->id, 'package.exported', $request->user());

        return response()->download($path, 'gaeld-archive-'.$package->id.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function authorizePackage(ArchivePackage $package, CurrentOrganization $organization, bool $manage = false): void
    {
        abort_unless($package->organization_id === $organization->id(), 404);
        $this->authorize($manage ? 'resume' : 'view', $package);
    }
}
