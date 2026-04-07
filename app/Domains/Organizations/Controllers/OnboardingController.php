<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\DTOs\CreateOrganizationData;
use App\Domains\Organizations\Requests\OnboardingRequest;
use App\Domains\Organizations\Services\OrganizationService;
use App\Domains\Organizations\Services\OrganizationSetupService;
use App\Http\Controllers\Controller;
use App\Support\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post-setup onboarding flow and getting-started checklist.
 */
class OnboardingController extends Controller
{
    public function create(Request $request): RedirectResponse|Response
    {
        // In SaaS mode, org creation happens during /signup — not here
        if (FeatureFlag::isSaas()) {
            return redirect()->route('signup');
        }

        if ($request->user()->organizations()->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/CreateOrganization');
    }

    public function store(
        OnboardingRequest $request,
        OrganizationService $organizationService,
        OrganizationSetupService $setupService,
    ): RedirectResponse {
        if (FeatureFlag::isSaas()) {
            return redirect()->route('signup');
        }

        $validated = $request->validated();

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
