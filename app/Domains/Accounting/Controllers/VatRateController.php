<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Accounting\Requests\StoreVatRateRequest;
use App\Domains\Accounting\Requests\UpdateVatRateRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD operations for organization-scoped VAT rate definitions.
 */
class VatRateController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', VatRate::class);

        $vatRates = VatRate::where('organization_id', $currentOrg->id())
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Accounting/VatRates', [
            'vatRates' => $vatRates,
        ]);
    }

    public function store(StoreVatRateRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', VatRate::class);

        $validated = $request->validated();

        $validated['organization_id'] = $currentOrg->id();
        $validated['is_active'] = true;

        if (! empty($validated['is_default'])) {
            VatRate::where('organization_id', $currentOrg->id())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        VatRate::create($validated);

        return redirect()->route('accounting.vatRates')
            ->with('success', __('app.vat_rate_created'));
    }

    public function update(UpdateVatRateRequest $request, VatRate $vatRate): RedirectResponse
    {
        $this->authorize('update', $vatRate);

        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            VatRate::where('organization_id', $vatRate->organization_id)
                ->where('id', '!=', $vatRate->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $vatRate->update($validated);

        return redirect()->route('accounting.vatRates')
            ->with('success', __('app.vat_rate_updated'));
    }

    public function destroy(VatRate $vatRate): RedirectResponse
    {
        $this->authorize('delete', $vatRate);

        $vatRate->delete();

        return redirect()->route('accounting.vatRates')
            ->with('success', __('app.vat_rate_deleted'));
    }
}
