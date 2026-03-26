<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Actions\UpdateOrganizationAction;
use App\Domains\Organizations\DTOs\UpdateOrganizationData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationSettingsController extends Controller
{
    public function show(CurrentOrganization $currentOrg): Response
    {
        $organization = $currentOrg->get();

        $this->authorize('update', $organization);

        return Inertia::render('Organizations/Settings', [
            'organization' => $organization,
            'hasLogo' => $organization->logo_path && Storage::disk('local')->exists($organization->logo_path),
        ]);
    }

    public function updateGeneral(Request $request, CurrentOrganization $currentOrg, UpdateOrganizationAction $action): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'string|size:3',
            'locale' => ['string', Rule::in(config('accounting.supported_locales'))],
            'require_two_factor' => 'sometimes|boolean',
            'default_payment_terms_days' => 'sometimes|integer|min:0|max:365',
        ]);

        $action->execute($organization, UpdateOrganizationData::fromArray($validated));

        return redirect()->route('settings')
            ->with('success', __('app.organization_updated'));
    }

    public function updateInvoice(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'invoice_header_text' => 'nullable|string|max:1000',
            'invoice_footer_text' => 'nullable|string|max:1000',
        ]);

        $organization->update($validated);

        return redirect()->route('settings')
            ->with('success', __('app.invoice_settings_updated'));
    }

    public function uploadLogo(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg|max:'.config('uploads.max_size.image'),
        ]);

        // Delete old logo if it exists
        if ($organization->logo_path && Storage::disk('local')->exists($organization->logo_path)) {
            Storage::disk('local')->delete($organization->logo_path);
        }

        $path = $request->file('logo')->store("logos/{$organization->id}", 'local');

        $organization->update(['logo_path' => $path]);

        return redirect()->route('settings')
            ->with('success', __('app.logo_uploaded'));
    }

    public function deleteLogo(CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        if ($organization->logo_path && Storage::disk('local')->exists($organization->logo_path)) {
            Storage::disk('local')->delete($organization->logo_path);
        }

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

    public function updateCommunications(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $organization = $currentOrg->get();
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'invoice_email_subject' => 'nullable|string|max:255',
            'invoice_email_body' => 'nullable|string|max:5000',
        ]);

        $organization->update($validated);

        return redirect()->route('settings')
            ->with('success', __('app.communication_settings_updated'));
    }
}
