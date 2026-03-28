<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VatRateController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', VatRate::class);

        $vatRates = VatRate::where('organization_id', $currentOrg->id())
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/VatRates', [
            'vatRates' => $vatRates,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', VatRate::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
        ]);

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

    public function update(Request $request, VatRate $vatRate): RedirectResponse
    {
        $this->authorize('update', $vatRate);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

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
