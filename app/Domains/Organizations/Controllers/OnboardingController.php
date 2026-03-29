<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-setup onboarding flow and getting-started checklist.
 */
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
        ChartTemplateService $chartTemplateService,
    ): RedirectResponse {
        $validTemplateKeys = $chartTemplateService->validKeys();

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
            'chart_of_accounts' => ['required', 'string', Rule::in([...$validTemplateKeys, 'none'])],
        ]);

        $org = DB::transaction(function () use ($request, $validated, $organizationService, $setupService) {
            $org = $organizationService->create(
                $request->user(),
                CreateOrganizationData::fromArray($validated),
            );

            if ($validated['chart_of_accounts'] !== 'none') {
                $setupService->seedChartOfAccounts($org, $validated['chart_of_accounts']);
            }

            return $org;
        });

        session(['current_organization_id' => $org->id]);

        return redirect()->route('dashboard')
            ->with('success', __('app.welcome_onboarding'));
    }
}
