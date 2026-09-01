<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Accounting\Queries\VatRateQuery;
use App\Domains\Expenses\Controllers\ExpenseCategoryController;
use App\Domains\Expenses\Queries\ExpenseCategoryQuery;
use App\Domains\Invoicing\Queries\InvoiceCatalogItemQuery;
use App\Domains\Organizations\Actions\ApplyFiscalYearChangeAction;
use App\Domains\Organizations\Actions\ApproveFiscalYearChangeAction;
use App\Domains\Organizations\Actions\RejectFiscalYearChangeAction;
use App\Domains\Organizations\Actions\RequestFiscalYearChangeAction;
use App\Domains\Organizations\Actions\UpdateOrganizationAction;
use App\Domains\Organizations\DTOs\UpdateCommunicationsData;
use App\Domains\Organizations\DTOs\UpdateInvoiceSettingsData;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Enums\BusinessType;
use App\Domains\Organizations\Enums\OrganizationModule;
use App\Domains\Organizations\Jobs\ExportOrganizationDataJob;
use App\Domains\Organizations\Models\FiscalYearChangeRequest;
use App\Domains\Organizations\Requests\RequestFiscalYearChangeRequest;
use App\Domains\Organizations\Requests\UpdateCommunicationsRequest;
use App\Domains\Organizations\Requests\UpdateInvoiceSettingsRequest;
use App\Domains\Organizations\Requests\UpdateOrganizationSettingsRequest;
use App\Domains\Organizations\Requests\UploadLogoRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationService;
use App\Http\Controllers\Controller;
use App\Support\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Organization settings page: general info, branding, and security policies.
 */
class OrganizationSettingsController extends Controller
{
    public function __construct(
        private FileUploadService $uploadService,
        private OrganizationService $organizationService,
    ) {}

    public function show(CurrentOrganization $currentOrg): Response
    {
        $organization = $currentOrg->get();

        $this->authorize('update', $organization);

        // Seed default expense categories if org has none yet
        if ($organization->expenseCategories()->count() === 0) {
            ExpenseCategoryController::seedDefaults($organization->id);
        }

        return Inertia::render('Organizations/Settings', [
            'organization' => $organization,
            'hasLogo' => $organization->logo_path && Storage::disk('local')->exists($organization->logo_path),
            'expenseCategories' => ExpenseCategoryQuery::all(),
            'catalogItems' => InvoiceCatalogItemQuery::all(),
            'vatRates' => VatRateQuery::active(),
            'modules' => OrganizationModule::values(),
            'modulePresets' => OrganizationModule::presets(),
            'pendingFiscalYearChange' => FiscalYearChangeRequest::query()
                ->whereIn('status', ['pending', 'approved'])
                ->latest()
                ->first(),
        ]);
    }

    public function requestFiscalYearChange(
        RequestFiscalYearChangeRequest $request,
        CurrentOrganization $currentOrg,
        RequestFiscalYearChangeAction $action,
    ): RedirectResponse {
        $organization = $currentOrg->get();

        $action->execute(
            $organization,
            $request->user(),
            $request->validated('requested_start'),
            $request->validated('reason'),
        );

        return redirect()->route('settings')
            ->with('success', __('app.fiscal_year_change_requested'));
    }

    public function approveFiscalYearChange(
        Request $request,
        FiscalYearChangeRequest $fiscalYearChangeRequest,
        ApproveFiscalYearChangeAction $action,
    ): RedirectResponse {
        $this->authorize('approve', $fiscalYearChangeRequest);

        $action->execute($fiscalYearChangeRequest, $request->user());

        return redirect()->route('settings')
            ->with('success', __('app.fiscal_year_change_approved'));
    }

    public function rejectFiscalYearChange(
        Request $request,
        FiscalYearChangeRequest $fiscalYearChangeRequest,
        RejectFiscalYearChangeAction $action,
    ): RedirectResponse {
        $this->authorize('reject', $fiscalYearChangeRequest);

        $action->execute($fiscalYearChangeRequest, $request->user());

        return redirect()->route('settings')
            ->with('success', __('app.fiscal_year_change_rejected'));
    }

    public function applyFiscalYearChange(
        FiscalYearChangeRequest $fiscalYearChangeRequest,
        CurrentOrganization $currentOrg,
        ApplyFiscalYearChangeAction $action,
    ): RedirectResponse {
        $this->authorize('apply', $fiscalYearChangeRequest);

        $action->execute($fiscalYearChangeRequest, $currentOrg->get());

        return redirect()->route('settings')
            ->with('success', __('app.fiscal_year_change_applied'));
    }

    public function updateGeneral(UpdateOrganizationSettingsRequest $request, CurrentOrganization $currentOrg, UpdateOrganizationAction $action): RedirectResponse
    {
        $organization = $currentOrg->get();

        $action->execute($organization, UpdateOrganizationData::fromArray($request->validated()));

        return redirect()->route('settings')
            ->with('success', __('app.organization_updated'));
    }

    public function updateInvoice(UpdateInvoiceSettingsRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $this->organizationService->updateInvoiceSettings($organization, UpdateInvoiceSettingsData::fromArray($request->validated()));

        return redirect()->route('settings')
            ->with('success', __('app.invoice_settings_updated'));
    }

    public function uploadLogo(UploadLogoRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        // Delete old logo if it exists
        $this->uploadService->delete($organization->logo_path);

        $path = $this->uploadService->store($request->file('logo'), "logos/{$organization->id}");

        $organization->update(['logo_path' => $path]);

        return redirect()->route('settings')
            ->with('success', __('app.logo_uploaded'));
    }

    public function deleteLogo(CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $this->uploadService->delete($organization->logo_path);

        $organization->update(['logo_path' => null]);

        return redirect()->route('settings')
            ->with('success', __('app.logo_removed'));
    }

    public function serveLogo(CurrentOrganization $currentOrg): StreamedResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('view', $organization);

        if (! $organization->logo_path || ! Storage::disk('local')->exists($organization->logo_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($organization->logo_path);
    }

    public function updateCommunications(UpdateCommunicationsRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $this->organizationService->updateCommunications($organization, UpdateCommunicationsData::fromArray($request->validated()));

        return redirect()->route('settings')
            ->with('success', __('app.communication_settings_updated'));
    }

    public function updateModules(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $allowed = OrganizationModule::values();
        $modules = collect((array) $request->input('modules', []))
            ->only($allowed)
            ->map(fn ($value) => (bool) $value)
            ->all();

        $data = ['enabled_modules' => $modules];

        $businessType = $request->input('business_type');
        if (in_array($businessType, BusinessType::values(), true)) {
            $data['business_type'] = $businessType;
        }

        $organization->update($data);

        return redirect()->route('settings')
            ->with('success', __('app.modules_updated'));
    }

    public function exportData(CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        ExportOrganizationDataJob::dispatch(
            $organization->id,
            (string) request()->user()->id,
        );

        return redirect()->route('settings')
            ->with('success', __('app.export_dispatched'));
    }

    public function downloadExport(Request $request): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $path = $request->query('path', '');
        $filename = basename($path);

        // Defense-in-depth: ensure org-scoped exports match the current organization.
        // The signed URL is the primary security mechanism; this prevents misuse if
        // a signed URL leaks to a user in a different organization.
        $currentOrg = app(CurrentOrganization::class);
        if (str_starts_with($filename, 'org-export-') && ! str_contains($filename, $currentOrg->id())) {
            abort(403, 'Export does not belong to this organization.');
        }

        $absolutePath = Storage::disk('local')->path('exports/'.$filename);

        abort_unless(file_exists($absolutePath), 404);

        return response()->download($absolutePath);
    }
}
