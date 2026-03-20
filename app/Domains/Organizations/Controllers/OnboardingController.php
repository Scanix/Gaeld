<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(Request $request): RedirectResponse|Response
    {
        if ($request->user()->organizations()->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/CreateOrganization');
    }

    public function store(
        Request $request,
        OrganizationService $organizationService,
        OrganizationSetupService $setupService,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'required|string|size:3',
            'locale' => 'required|string|in:en,fr,de,it,rm',
            'chart_of_accounts' => 'required|string|in:swiss_sme,none',
        ]);

        DB::transaction(function () use ($request, $validated, $organizationService, $setupService) {
            $org = $organizationService->create(
                $request->user(),
                CreateOrganizationData::fromArray($validated),
            );

            session(['current_organization_id' => $org->id]);

            if ($validated['chart_of_accounts'] === 'swiss_sme') {
                $setupService->seedSwissDefaults($org);
            }
        });

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to Gäld! Your organization is ready.');
    }
}
