<?php

namespace App\Domains\Organizations\Controllers;

use App\Domains\Organizations\Actions\CompleteSetupAction;
use App\Domains\Organizations\DTOs\CompleteSetupData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Requests\SetupWizardRequest;
use App\Http\Controllers\Controller;
use App\Support\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Initial setup wizard: creates the first user and organization.
 *
 * Only accessible when no organization exists in the database.
 * Disabled in SaaS mode — tenants must use /signup instead.
 */
class SetupWizardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        if (FeatureFlag::isSaas()) {
            return redirect()->route('signup');
        }

        // Redirect if already set up
        if (Organization::exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Setup/Wizard');
    }

    public function store(SetupWizardRequest $request, CompleteSetupAction $completeSetupAction): RedirectResponse
    {
        if (FeatureFlag::isSaas()) {
            return redirect()->route('signup');
        }

        if (Organization::exists()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validated();

        $user = $completeSetupAction->execute(CompleteSetupData::fromArray($validated));
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', __('app.welcome_setup'));
    }
}
